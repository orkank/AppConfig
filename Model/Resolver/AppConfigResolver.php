<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use IDangerous\AppConfig\Model\ResourceModel\KeyValue\CollectionFactory as KeyValueCollectionFactory;
use IDangerous\AppConfig\Model\ResourceModel\Group\CollectionFactory as GroupCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\UrlInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Catalog\Model\Product;

class AppConfigResolver implements ResolverInterface
{
    /**
     * @var KeyValueCollectionFactory
     */
    protected $keyValueCollectionFactory;

    /**
     * @var GroupCollectionFactory
     */
    protected $groupCollectionFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var StockRegistryInterface
     */
    protected $stockRegistry;

    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @param KeyValueCollectionFactory $keyValueCollectionFactory
     * @param GroupCollectionFactory $groupCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param ProductRepositoryInterface $productRepository
     * @param StockRegistryInterface $stockRegistry
     * @param PriceCurrencyInterface $priceCurrency
     */
    public function __construct(
        KeyValueCollectionFactory $keyValueCollectionFactory,
        GroupCollectionFactory $groupCollectionFactory,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        ProductRepositoryInterface $productRepository,
        StockRegistryInterface $stockRegistry,
        PriceCurrencyInterface $priceCurrency
    ) {
        $this->keyValueCollectionFactory = $keyValueCollectionFactory;
        $this->groupCollectionFactory = $groupCollectionFactory;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->productRepository = $productRepository;
        $this->stockRegistry = $stockRegistry;
        $this->priceCurrency = $priceCurrency;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if (!$this->isEnabled()) {
            return [];
        }

        $appVersion = $args['app_version'] ?? null;
        $keys = $args['keys'] ?? [];
        $groupsFilter = $args['groups'] ?? [];

        // 1. Get Active Groups for version checking
        $activeGroups = [];
        $groupCollection = $this->groupCollectionFactory->create();
        $groupCollection->addFieldToFilter('is_active', 1);
        foreach ($groupCollection as $group) {
            $activeGroups[$group->getId()] = [
                'code' => $group->getCode(),
                'version' => $group->getVersion()
            ];
        }

        // 2. Prepare Key Value Collection
        $collection = $this->keyValueCollectionFactory->create();
        $collection->joinGroup();
        $collection->addFieldToFilter('main_table.is_active', 1);

        // Filter by keys if provided
        if (!empty($keys)) {
            $collection->addFieldToFilter('main_table.key_name', ['in' => $keys]);
        }

        // Filter by groups if provided
        if (!empty($groupsFilter)) {
            // Because we joined the group table, we can filter by group code
            $collection->addFieldToFilter('group.code', ['in' => $groupsFilter]);
        }

        $result = [];
        foreach ($collection as $item) {
            // Determine version requirement
            $itemGroupId = $item->getGroupId();
            $groupVersion = null;
            if ($itemGroupId && isset($activeGroups[$itemGroupId])) {
                $groupVersion = $activeGroups[$itemGroupId]['version'];
            }

            // Check compatibility
            $keyValueVersion = $item->getVersion();
            $versionToCheck = $keyValueVersion ?: $groupVersion;

            if (!$this->isVersionCompatible($appVersion, $versionToCheck)) {
                continue;
            }

            // Prepare all value types
            $textValue = $item->getTextValue() ?? '';

            $fileValue = '';
            if ($item->getFilePath()) {
                $mediaUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
                $fileValue = rtrim($mediaUrl, '/') . '/' . ltrim($item->getFilePath(), '/');
            }

            $jsonValue = null;
            $jsonValueStr = $item->getJsonValue() ?? '';
            if (!empty($jsonValueStr)) {
                // Return as JSON string - client will parse it
                // Validate it's valid JSON first
                $decoded = json_decode($jsonValueStr, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    // Valid JSON, return as string for GraphQL
                    $jsonValue = $jsonValueStr;
                } else {
                    // Invalid JSON, return as-is (might be malformed)
                    $jsonValue = $jsonValueStr;
                }
            }

            $productsValue = null;
            $productsValueStr = $item->getProductsValue() ?? '';
            if (!empty($productsValueStr)) {
                $decoded = json_decode($productsValueStr, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    // Return full product objects with price and stock info
                    $productsValue = [];
                    foreach ($decoded as $productData) {
                        $productId = null;
                        $sku = '';
                        $name = '';

                        if (is_array($productData)) {
                            $productId = isset($productData['id']) ? (int)$productData['id'] : null;
                            $sku = $productData['sku'] ?? '';
                            $name = $productData['name'] ?? '';
                        } elseif (is_numeric($productData)) {
                            $productId = (int)$productData;
                        }

                        if (!$productId) {
                            continue;
                        }

                        // Load product to get price and stock info
                        $productInfo = [
                            'id' => $productId,
                            'sku' => $sku,
                            'name' => $name,
                            'final_price' => 0.0,
                            'regular_price' => 0.0,
                            'currency' => $this->priceCurrency->getCurrency()->getCurrencyCode(),
                            'is_in_stock' => false,
                            'qty' => 0.0
                        ];

                        try {
                            // Load product by ID
                            $product = $this->productRepository->getById($productId, false, $this->storeManager->getStore()->getId());

                            // Update SKU and name if not provided
                            if (empty($sku)) {
                                $productInfo['sku'] = $product->getSku();
                            }
                            if (empty($name)) {
                                $productInfo['name'] = $product->getName();
                            }

                            // Get price information
                            try {
                                $finalPrice = $product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
                                $regularPrice = $product->getPriceInfo()->getPrice('regular_price')->getAmount()->getValue();
                                $productInfo['final_price'] = (float)$finalPrice;
                                $productInfo['regular_price'] = (float)$regularPrice;
                            } catch (\Exception $e) {
                                // Price info not available, keep defaults
                            }

                            // Get stock information
                            try {
                                $stockItem = $this->stockRegistry->getStockItem($productId);
                                $productInfo['is_in_stock'] = (bool)$stockItem->getIsInStock();
                                $productInfo['qty'] = (float)$stockItem->getQty();
                            } catch (\Exception $e) {
                                // Stock info not available, keep defaults
                            }
                        } catch (\Exception $e) {
                            // Product not found or error loading, use provided data only
                        }

                        $productsValue[] = $productInfo;
                    }

                    // Return null if empty
                    if (empty($productsValue)) {
                        $productsValue = null;
                    }
                }
            }

            $categoriesValue = null;
            $categoriesValueStr = $item->getCategoriesValue() ?? '';
            if (!empty($categoriesValueStr)) {
                $decoded = json_decode($categoriesValueStr, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    // Return full category objects (id, name) - same as REST API
                    $categoriesValue = array_map(function($category) {
                        if (is_array($category)) {
                            // Ensure id is integer
                            if (isset($category['id'])) {
                                $category['id'] = is_numeric($category['id']) ? (int)$category['id'] : null;
                            }
                            return [
                                'id' => isset($category['id']) && is_numeric($category['id']) ? (int)$category['id'] : null,
                                'name' => $category['name'] ?? ''
                            ];
                        } elseif (is_numeric($category)) {
                            // If it's just a numeric ID, return minimal object
                            return [
                                'id' => (int)$category,
                                'name' => ''
                            ];
                        }
                        return null;
                    }, $decoded);
                    // Filter out null values
                    $categoriesValue = array_filter($categoriesValue, function($category) {
                        return $category !== null && isset($category['id']);
                    });
                    // Re-index array
                    $categoriesValue = array_values($categoriesValue);
                    // Return null if empty
                    if (empty($categoriesValue)) {
                        $categoriesValue = null;
                    }
                }
            }

            // Get group code from joined data
            $groupCode = $item->getData('group_code') ?? null;

            // Get value type
            $valueType = $item->getValueType() ?? 'text';

            $result[] = [
                'key' => $item->getKeyName(),
                'group' => $groupCode,
                'type' => $valueType,
                'text' => $textValue,
                'file' => $fileValue,
                'json' => $jsonValue,
                'products' => $productsValue,
                'categories' => $categoriesValue,
                'version' => $item->getVersion()
            ];
        }

        return $result;
    }

    /**
     * Check if module is enabled
     *
     * @return bool
     */
    protected function isEnabled()
    {
        return $this->scopeConfig->isSetFlag(
            'appconfig/general/enabled',
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Check version compatibility (SemVer/BuildNumber)
     */
    protected function isVersionCompatible($appVersion, $configVersion)
    {
        if (empty($configVersion)) {
            return true;
        }
        if (empty($appVersion)) {
            return false;
        }

        $appParts = $this->parseVersion($appVersion);
        $configParts = $this->parseVersion($configVersion);

        if (!$appParts || !$configParts) {
            return true;
        }

        $appVal = $appParts['major'] * 10000 + $appParts['minor'] * 100 + $appParts['patch'];
        $configVal = $configParts['major'] * 10000 + $configParts['minor'] * 100 + $configParts['patch'];

        if ($appVal < $configVal) {
            return false;
        }

        if ($appVal == $configVal && isset($appParts['build']) && isset($configParts['build'])) {
            return $appParts['build'] >= $configParts['build'];
        }

        return true;
    }

    protected function parseVersion($version)
    {
        if (preg_match('/^(\d+)\.(\d+)\.(\d+)(?:\+(\d+))?$/', $version, $matches)) {
            return [
                'major' => (int)$matches[1],
                'minor' => (int)$matches[2],
                'patch' => (int)$matches[3],
                'build' => isset($matches[4]) ? (int)$matches[4] : null
            ];
        }
        return null;
    }
}

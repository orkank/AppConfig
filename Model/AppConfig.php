<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Model;

use IDangerous\AppConfig\Api\AppConfigInterface;
use IDangerous\AppConfig\Api\Data\ConfigDataFactory;
use IDangerous\AppConfig\Api\Data\GroupDataFactory;
use IDangerous\AppConfig\Model\ResourceModel\Group\CollectionFactory as GroupCollectionFactory;
use IDangerous\AppConfig\Model\ResourceModel\KeyValue\CollectionFactory as KeyValueCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Product as ProductHelper;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Cms\Api\PageRepositoryInterface;

class AppConfig implements AppConfigInterface
{
    /**
     * @var ConfigDataFactory
     */
    protected $configDataFactory;

    /**
     * @var GroupDataFactory
     */
    protected $groupDataFactory;

    /**
     * @var GroupCollectionFactory
     */
    protected $groupCollectionFactory;

    /**
     * @var KeyValueCollectionFactory
     */
    protected $keyValueCollectionFactory;

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
     * @var ProductHelper
     */
    protected $productHelper;

    /**
     * @var PageRepositoryInterface
     */
    protected $pageRepository;

    /**
     * @var ProductCollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * @param ConfigDataFactory $configDataFactory
     * @param GroupDataFactory $groupDataFactory
     * @param GroupCollectionFactory $groupCollectionFactory
     * @param KeyValueCollectionFactory $keyValueCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     * @param ProductRepositoryInterface $productRepository
     * @param StockRegistryInterface $stockRegistry
     * @param PriceCurrencyInterface $priceCurrency
     * @param ProductHelper $productHelper
     * @param PageRepositoryInterface $pageRepository
     * @param ProductCollectionFactory $productCollectionFactory
     */
    public function __construct(
        ConfigDataFactory $configDataFactory,
        GroupDataFactory $groupDataFactory,
        GroupCollectionFactory $groupCollectionFactory,
        KeyValueCollectionFactory $keyValueCollectionFactory,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        ProductRepositoryInterface $productRepository,
        StockRegistryInterface $stockRegistry,
        PriceCurrencyInterface $priceCurrency,
        ProductHelper $productHelper,
        PageRepositoryInterface $pageRepository,
        ProductCollectionFactory $productCollectionFactory
    ) {
        $this->configDataFactory = $configDataFactory;
        $this->groupDataFactory = $groupDataFactory;
        $this->groupCollectionFactory = $groupCollectionFactory;
        $this->keyValueCollectionFactory = $keyValueCollectionFactory;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->productRepository = $productRepository;
        $this->stockRegistry = $stockRegistry;
        $this->priceCurrency = $priceCurrency;
        $this->productHelper = $productHelper;
        $this->pageRepository = $pageRepository;
        $this->productCollectionFactory = $productCollectionFactory;
    }

    /**
     * @inheritDoc
     */
    public function getConfig($appVersion = null, $groupCode = null)
    {
        if (!$this->isEnabled()) {
            throw new LocalizedException(__('App Config module is disabled.'));
        }

        $defaults = [];
        $groups = [];

        $keyValueCollection = $this->keyValueCollectionFactory->create();
        // Join first, then add filters with table prefixes
        $keyValueCollection->joinGroup();
        $keyValueCollection->addFieldToFilter('main_table.is_active', 1);

        if ($groupCode) {
            $keyValueCollection->addFieldToFilter('group.code', $groupCode);
        }

        // Get all active groups for version check
        $activeGroups = [];
        $groupCollection = $this->groupCollectionFactory->create();
        $groupCollection->addFieldToFilter('is_active', 1);
        foreach ($groupCollection as $group) {
            if ($this->isVersionCompatible($appVersion, $group->getVersion())) {
                $activeGroups[$group->getId()] = [
                    'code' => $group->getCode(),
                    'name' => $group->getName(),
                    'description' => $group->getDescription(),
                    'version' => $group->getVersion()
                ];
            }
        }

        foreach ($keyValueCollection as $item) {
            $itemGroupCode = $item->getData('group_code');
            $itemGroupId = $item->getGroupId();
            $groupVersion = null;

            // Get group version if group exists
            if ($itemGroupId && isset($activeGroups[$itemGroupId])) {
                $groupVersion = $activeGroups[$itemGroupId]['version'];
            }

            // Version check - check both key-value version and group version
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
                $decoded = json_decode($jsonValueStr, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $jsonValue = $decoded;
                } else {
                    $jsonValue = $jsonValueStr;
                }
            }

            $productsValue = null;
            $productsValueStr = $item->getProductsValue() ?? '';
            if (!empty($productsValueStr)) {
                $decoded = json_decode($productsValueStr, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    // Enhance products with price and stock information
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

                        // Start with provided data
                        $productInfo = [
                            'id' => $productId,
                            'sku' => $sku,
                            'name' => $name,
                            'image' => null,
                            'media_gallery' => [],
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

                            // Get image URL (main product image, or placeholder if none)
                            try {
                                $imageUrl = $this->productHelper->getImageUrl($product);
                                $productInfo['image'] = $imageUrl ? (string)$imageUrl : null;
                            } catch (\Exception $e) {
                                // Image not available, keep null
                            }

                            // Get media gallery - all images in full/original size
                            try {
                                $galleryImages = $product->getMediaGalleryImages();
                                if ($galleryImages instanceof \Magento\Framework\Data\Collection) {
                                    $mediaGallery = [];
                                    foreach ($galleryImages as $galleryImage) {
                                        $url = $galleryImage->getData('url');
                                        if ($url) {
                                            $mediaGallery[] = [
                                                'url' => (string)$url,
                                                'label' => (string)($galleryImage->getData('label') ?? $galleryImage->getLabel() ?? '')
                                            ];
                                        }
                                    }
                                    $productInfo['media_gallery'] = $mediaGallery;
                                }
                            } catch (\Exception $e) {
                                // Media gallery not available, keep empty array
                            }

                            $customAttrCodes = $item->getProductCustomAttributesList();
                            if (!empty($customAttrCodes)) {
                                $productInfo = $this->addCustomAttributesToProductInfo($productInfo, $product, $customAttrCodes);
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
                    $categoriesValue = [];
                    foreach ($decoded as $catData) {
                        $catId = is_array($catData) ? ((int)($catData['id'] ?? 0)) : (int)$catData;
                        $catName = is_array($catData) ? ($catData['name'] ?? '') : '';
                        $productLimit = is_array($catData) ? ((int)($catData['product_limit'] ?? 0)) : 0;

                        $categoryOut = [
                            'id' => $catId,
                            'name' => $catName
                        ];

                        if ($productLimit > 0 && $catId) {
                            $customAttrCodes = $item->getProductCustomAttributesList();
                            $categoryProducts = $this->getProductsFromCategory($catId, $productLimit, $customAttrCodes);
                            $categoryOut['products'] = $categoryProducts;
                        }

                        $categoriesValue[] = $categoryOut;
                    }
                }
            }

            $cmsPagesValue = null;
            $cmsPagesValueStr = $item->getCmsPagesValue() ?? '';
            $includeContent = (bool) $item->getCmsIncludeContent();
            if (!empty($cmsPagesValueStr)) {
                $decoded = json_decode($cmsPagesValueStr, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $cmsPagesValue = [];
                    foreach ($decoded as $pageData) {
                        $pageId = null;
                        if (is_array($pageData) && isset($pageData['id'])) {
                            $pageId = (int) $pageData['id'];
                        } elseif (is_numeric($pageData)) {
                            $pageId = (int) $pageData;
                        }
                        if (!$pageId) {
                            continue;
                        }
                        try {
                            $page = $this->pageRepository->getById($pageId);
                            $pageInfo = [
                                'id' => (int) $page->getId(),
                                'permalink' => $page->getIdentifier(),
                                'title' => $page->getTitle(),
                                'update_time' => $page->getUpdateTime()
                            ];
                            if ($includeContent) {
                                $pageInfo['content'] = $page->getContent();
                            }
                            $cmsPagesValue[] = $pageInfo;
                        } catch (\Exception $e) {
                            // Page not found, skip
                        }
                    }
                    if (empty($cmsPagesValue)) {
                        $cmsPagesValue = null;
                    }
                }
            }

            $keyValueData = [
                'key' => $item->getKeyName(),
                'text' => $textValue,
                'file' => $fileValue,
                'json' => $jsonValue,
                'products' => $productsValue,
                'categories' => $categoriesValue,
                'cms_pages' => $cmsPagesValue,
                'version' => $item->getVersion()
            ];

            if ($itemGroupCode && $itemGroupId && isset($activeGroups[$itemGroupId])) {
                // Add to group
                if (!isset($groups[$itemGroupCode])) {
                    $groups[$itemGroupCode] = [
                        'name' => $activeGroups[$itemGroupId]['name'],
                        'description' => $activeGroups[$itemGroupId]['description'],
                        'version' => $activeGroups[$itemGroupId]['version'],
                        'configs' => []
                    ];
                }
                $groups[$itemGroupCode]['configs'][$item->getKeyName()] = $keyValueData;
            } else {
                // Add to defaults (no group)
                $defaults[$item->getKeyName()] = $keyValueData;
            }
        }

        // Return as associative array - Magento will serialize it as JSON object
        return [
            'DEFAULTS' => $defaults,
            'GROUPS' => $groups
        ];
    }

    /**
     * Add custom attributes to product info array (only attributes that exist in system)
     *
     * @param array $productInfo
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     * @param string[] $attrCodes
     * @return array
     */
    protected function addCustomAttributesToProductInfo(array $productInfo, $product, array $attrCodes): array
    {
        foreach ($attrCodes as $code) {
            $code = trim($code);
            if ($code === '') {
                continue;
            }
            $attr = $product->getResource()->getAttribute($code);
            if (!$attr) {
                continue;
            }
            $val = $product->getAttributeText($code);
            if ($val === null || $val === false) {
                $val = $product->getData($code);
            }
            if ($val !== null && $val !== '') {
                $productInfo[$code] = is_array($val) ? $val : (is_scalar($val) ? $val : (string) $val);
            }
        }
        return $productInfo;
    }

    /**
     * Get products from category with enrichment (price, stock, image)
     *
     * @param int $categoryId
     * @param int $limit
     * @param string[] $customAttrCodes
     * @return array
     */
    protected function getProductsFromCategory(int $categoryId, int $limit, array $customAttrCodes = []): array
    {
        $result = [];
        try {
            $storeId = $this->storeManager->getStore()->getId();
            $collection = $this->productCollectionFactory->create();
            $collection->setStoreId($storeId)
                ->addAttributeToSelect(['name', 'sku', 'price'])
                ->addCategoriesFilter(['in' => [$categoryId]])
                ->setPageSize($limit)
                ->setCurPage(1);

            foreach ($collection as $product) {
                $productId = (int) $product->getId();
                $productInfo = [
                    'id' => $productId,
                    'sku' => $product->getSku(),
                    'name' => $product->getName(),
                    'image' => null,
                    'media_gallery' => [],
                    'final_price' => 0.0,
                    'regular_price' => 0.0,
                    'currency' => $this->priceCurrency->getCurrency()->getCurrencyCode(),
                    'is_in_stock' => false,
                    'qty' => 0.0
                ];

                try {
                    $fullProduct = $this->productRepository->getById($productId, false, $storeId);

                    try {
                        $finalPrice = $fullProduct->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
                        $regularPrice = $fullProduct->getPriceInfo()->getPrice('regular_price')->getAmount()->getValue();
                        $productInfo['final_price'] = (float) $finalPrice;
                        $productInfo['regular_price'] = (float) $regularPrice;
                    } catch (\Exception $e) {
                        // Keep defaults
                    }

                    try {
                        $stockItem = $this->stockRegistry->getStockItem($productId);
                        $productInfo['is_in_stock'] = (bool) $stockItem->getIsInStock();
                        $productInfo['qty'] = (float) $stockItem->getQty();
                    } catch (\Exception $e) {
                        // Keep defaults
                    }

                    try {
                        $imageUrl = $this->productHelper->getImageUrl($fullProduct);
                        $productInfo['image'] = $imageUrl ? (string) $imageUrl : null;
                    } catch (\Exception $e) {
                        // Keep null
                    }

                    try {
                        $galleryImages = $fullProduct->getMediaGalleryImages();
                        if ($galleryImages instanceof \Magento\Framework\Data\Collection) {
                            foreach ($galleryImages as $galleryImage) {
                                $url = $galleryImage->getData('url');
                                if ($url) {
                                    $productInfo['media_gallery'][] = [
                                        'url' => (string) $url,
                                        'label' => (string) ($galleryImage->getData('label') ?? $galleryImage->getLabel() ?? '')
                                    ];
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        // Keep empty
                    }

                    if (!empty($customAttrCodes)) {
                        $productInfo = $this->addCustomAttributesToProductInfo($productInfo, $fullProduct, $customAttrCodes);
                    }
                } catch (\Exception $e) {
                    // Use basic info only
                }

                $result[] = $productInfo;
            }
        } catch (\Exception $e) {
            // Return empty on error
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function getGroups($appVersion = null)
    {
        if (!$this->isEnabled()) {
            throw new LocalizedException(__('App Config module is disabled.'));
        }

        $result = [];

        $groupCollection = $this->groupCollectionFactory->create();
        $groupCollection->addFieldToFilter('is_active', 1);

        foreach ($groupCollection as $group) {
            // Version check
            if (!$this->isVersionCompatible($appVersion, $group->getVersion())) {
                continue;
            }

            $result[$group->getCode()] = [
                'name' => $group->getName(),
                'description' => $group->getDescription(),
                'version' => $group->getVersion()
            ];
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function getValue($key, $groupCode = null, $appVersion = null)
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $collection = $this->keyValueCollectionFactory->create();
        $collection->joinGroup();
        $collection->addFieldToFilter('main_table.is_active', 1);
        $collection->addFieldToFilter('main_table.key_name', $key);

        if ($groupCode) {
            $collection->addFieldToFilter('group.code', $groupCode);
        }

        $collection->setPageSize(1)->setCurPage(1);
        $item = $collection->getFirstItem();

        if (!$item->getId()) {
            return null;
        }

        // Verify version compatibility
        // Need to check Group Version first
        $groupVersion = null;
        if ($item->getGroupId()) {
            // We need to fetch group version. Since we joined, check if it's available in data
            // The join usually prefixes group columns?
            // checking joinGroup implementation in Collection.
            // Assuming joinGroup uses 'group' alias.
            $groupVersion = $item->getData('group_version'); // Need to ensure join adds this or we fetch it.
        }

        // If 'group_version' is not in the joined data, we might need to load the group.
        // Let's assume for safety we load the group if group_id is present and we care about version
        if ($appVersion && $item->getGroupId()) {
             $group = $this->groupCollectionFactory->create()->getItemById($item->getGroupId());
             if ($group && $group->getId()) {
                 $groupVersion = $group->getVersion();
             }
        }

        $versionToCheck = $item->getVersion() ?: $groupVersion;
        if (!$this->isVersionCompatible($appVersion, $versionToCheck)) {
            return null;
        }

        // Process value
        $value = $item->getValue();
        if ($item->getValueType() === 'file' && $item->getFilePath()) {
            $mediaUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
            $value = rtrim($mediaUrl, '/') . '/' . ltrim($item->getFilePath(), '/');
        } elseif ($item->getValueType() === 'json' && $value) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }

        return $value;
    }

    /**
     * Check if app version is compatible with config version
     *
     * @param string|null $appVersion
     * @param string|null $configVersion
     * @return bool
     */
    protected function isVersionCompatible($appVersion, $configVersion)
    {
        // If no version specified in config, it's compatible
        if (empty($configVersion)) {
            return true;
        }

        // If no app version provided, return only null versions
        if (empty($appVersion)) {
            return false;
        }

        // Parse versions (format: 4.0.10+80)
        $appParts = $this->parseVersion($appVersion);
        $configParts = $this->parseVersion($configVersion);

        if (!$appParts || !$configParts) {
            return true; // If parsing fails, allow it
        }

        // Compare major.minor.patch
        $appVersionNum = $appParts['major'] * 10000 + $appParts['minor'] * 100 + $appParts['patch'];
        $configVersionNum = $configParts['major'] * 10000 + $configParts['minor'] * 100 + $configParts['patch'];

        // App version must be >= config version
        if ($appVersionNum < $configVersionNum) {
            return false;
        }

        // If versions are equal, compare build number
        if ($appVersionNum == $configVersionNum && isset($appParts['build']) && isset($configParts['build'])) {
            return $appParts['build'] >= $configParts['build'];
        }

        return true;
    }

    /**
     * Parse version string
     *
     * @param string $version
     * @return array|null
     */
    protected function parseVersion($version)
    {
        // Format: 4.0.10+80 or 4.0.10
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
}



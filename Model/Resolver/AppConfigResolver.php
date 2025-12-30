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
     * @param KeyValueCollectionFactory $keyValueCollectionFactory
     * @param GroupCollectionFactory $groupCollectionFactory
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        KeyValueCollectionFactory $keyValueCollectionFactory,
        GroupCollectionFactory $groupCollectionFactory,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->keyValueCollectionFactory = $keyValueCollectionFactory;
        $this->groupCollectionFactory = $groupCollectionFactory;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
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
                    $productsValue = $decoded;
                }
            }

            $categoriesValue = null;
            $categoriesValueStr = $item->getCategoriesValue() ?? '';
            if (!empty($categoriesValueStr)) {
                $decoded = json_decode($categoriesValueStr, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $categoriesValue = $decoded;
                }
            }

            $result[] = [
                'key' => $item->getKeyName(),
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

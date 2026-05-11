<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Model\Headless;

use IDangerous\AppConfig\Model\GroupFactory;
use IDangerous\AppConfig\Model\KeyValueFactory as KeyValueModelFactory;
use IDangerous\AppConfig\Model\ResourceModel\Group\CollectionFactory as GroupCollectionFactory;
use IDangerous\AppConfig\Model\ResourceModel\KeyValue\CollectionFactory as KeyValueCollectionFactory;
use Magento\Framework\Webapi\Exception as WebapiException;

class JsonKeyValueSync
{
    public function __construct(
        private GroupCollectionFactory $groupCollectionFactory,
        private GroupFactory $groupFactory,
        private KeyValueCollectionFactory $keyValueCollectionFactory,
        private KeyValueModelFactory $keyValueFactory,
        private HeadlessConfig $headlessConfig
    ) {
    }

    /**
     * Upsert JSON value for prefixed key bound to configured headless group.
     *
     * @throws WebapiException
     */
    public function upsertJson(string $key, mixed $jsonPayload, ?int $storeScopeId): void
    {
        $prefix = $this->headlessConfig->getKeyPrefix($storeScopeId);
        if ($prefix !== '' && \strpos($key, $prefix) !== 0) {
            throw new WebapiException(
                __('Invalid key.'),
                0,
                WebapiException::HTTP_BAD_REQUEST
            );
        }

        $groupId = $this->getOrCreateHeadlessGroupId((string) $this->headlessConfig->getGroupCode($storeScopeId));
        try {
            $jsonString = \is_string($jsonPayload)
                ? $jsonPayload
                : (string) \json_encode($jsonPayload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new WebapiException(
                __('Unable to serialize JSON.'),
                0,
                WebapiException::HTTP_BAD_REQUEST
            );
        }

        $coll = $this->keyValueCollectionFactory->create();
        $coll->addFieldToFilter('key_name', $key)->addFieldToFilter('group_id', $groupId)->setPageSize(1);

        $model = $coll->getFirstItem();
        if (!$model || !$model->getId()) {
            $model = $this->keyValueFactory->create();
            $model->setKeyName($key);
            $model->setGroupId((int) $groupId);
        }

        $model->setData('text_value', '');
        $model->setData('file_path', '');
        $model->setData('products_value', '');
        $model->setData('categories_value', '');
        $model->setData('cms_pages_value', '');
        $model->setData('cms_include_content', 0);
        $model->setData('json_value', $jsonString);
        $model->setData('value_type', 'json');
        $model->setData('origin', Origin::HEADLESS);
        $model->setData('is_active', 1);
        try {
            $model->save();
        } catch (\Throwable $e) {
            throw new WebapiException(
                __('Unable to save configuration.'),
                0,
                WebapiException::HTTP_INTERNAL_ERROR
            );
        }
    }

    /**
     * @param string[] $keys
     * @return array<string, string|null>
     */
    public function readKeys(array $keys, ?int $storeScopeId): array
    {
        $prefix = $this->headlessConfig->getKeyPrefix($storeScopeId);
        $groupId = $this->getOrCreateHeadlessGroupId((string) $this->headlessConfig->getGroupCode($storeScopeId));
        $keys = \array_values(\array_unique(\array_filter(\array_map('strval', $keys))));
        if (!$keys) {
            throw new WebapiException(__('Keys are required.'), 0, WebapiException::HTTP_BAD_REQUEST);
        }

        foreach ($keys as $k) {
            if ($prefix !== '' && \strpos((string) $k, $prefix) !== 0) {
                throw new WebapiException(__('Invalid key.'), 0, WebapiException::HTTP_BAD_REQUEST);
            }
        }

        $coll = $this->keyValueCollectionFactory->create();
        $coll->addFieldToFilter('key_name', ['in' => $keys])
            ->addFieldToFilter('group_id', (int) $groupId)
            ->addFieldToFilter('origin', Origin::HEADLESS);

        $coll->load();
        $found = [];
        foreach ($coll as $kv) {
            $found[(string) $kv->getKeyName()] = $kv->getJsonValue() !== null ? (string) $kv->getJsonValue() : '';
        }

        $results = [];
        foreach ($keys as $k) {
            $results[$k] = $found[$k] ?? null;
        }
        return $results;
    }

    /**
     * Permanently delete headless-origin JSON key rows (admin-created keys are untouched).
     *
     * @param string[] $keys
     * @return int Number of rows deleted
     * @throws WebapiException
     */
    public function deleteHeadlessKeys(array $keys, ?int $storeScopeId): int
    {
        $prefix = $this->headlessConfig->getKeyPrefix($storeScopeId);
        $keys = \array_values(\array_unique(\array_filter(\array_map('strval', $keys))));
        if (!$keys) {
            throw new WebapiException(__('keys are required.'), 0, WebapiException::HTTP_BAD_REQUEST);
        }

        foreach ($keys as $k) {
            if ($prefix !== '' && \strpos((string) $k, $prefix) !== 0) {
                throw new WebapiException(__('Invalid key.'), 0, WebapiException::HTTP_BAD_REQUEST);
            }
        }

        $groupId = $this->findHeadlessGroupId((string) $this->headlessConfig->getGroupCode($storeScopeId));
        if ($groupId === null) {
            return 0;
        }

        $coll = $this->keyValueCollectionFactory->create();
        $coll
            ->addFieldToFilter('key_name', ['in' => $keys])
            ->addFieldToFilter('group_id', $groupId)
            ->addFieldToFilter('origin', Origin::HEADLESS);
        $coll->load();

        $deleted = 0;
        foreach ($coll as $kv) {
            try {
                $kv->delete();
                ++$deleted;
            } catch (\Throwable $e) {
                throw new WebapiException(
                    __('Unable to delete configuration.'),
                    0,
                    WebapiException::HTTP_INTERNAL_ERROR
                );
            }
        }

        return $deleted;
    }

    private function findHeadlessGroupId(string $code): ?int
    {
        $groupColl = $this->groupCollectionFactory->create();
        $groupColl->addFieldToFilter('code', $code)->setPageSize(1);
        $existing = $groupColl->getFirstItem();
        if ($existing && $existing->getId()) {
            return (int) $existing->getId();
        }

        return null;
    }

    private function getOrCreateHeadlessGroupId(string $code): int
    {
        $found = $this->findHeadlessGroupId($code);
        if ($found !== null) {
            return $found;
        }

        $model = $this->groupFactory->create();
        $model->setData([
            'name' => 'Headless (auto)',
            'code' => $code,
            'description' => 'Created automatically by headless integration',
            'is_active' => 1,
            'version' => null,
        ]);
        try {
            $model->save();
        } catch (\Throwable $e) {
            throw new WebapiException(
                __('Unable to create configuration group.'),
                0,
                WebapiException::HTTP_INTERNAL_ERROR
            );
        }
        return (int) $model->getId();
    }
}

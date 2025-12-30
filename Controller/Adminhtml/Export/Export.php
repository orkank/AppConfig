<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Controller\Adminhtml\Export;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\RawFactory;
use IDangerous\AppConfig\Model\ResourceModel\Group\CollectionFactory as GroupCollectionFactory;
use IDangerous\AppConfig\Model\ResourceModel\KeyValue\CollectionFactory as KeyValueCollectionFactory;

class Export extends Action
{
    /**
     * Authorization level
     */
    const ADMIN_RESOURCE = 'IDangerous_AppConfig::export';

    /**
     * @var RawFactory
     */
    protected $resultRawFactory;

    /**
     * @var GroupCollectionFactory
     */
    protected $groupCollectionFactory;

    /**
     * @var KeyValueCollectionFactory
     */
    protected $keyValueCollectionFactory;

    /**
     * @param Context $context
     * @param RawFactory $resultRawFactory
     * @param GroupCollectionFactory $groupCollectionFactory
     * @param KeyValueCollectionFactory $keyValueCollectionFactory
     */
    public function __construct(
        Context $context,
        RawFactory $resultRawFactory,
        GroupCollectionFactory $groupCollectionFactory,
        KeyValueCollectionFactory $keyValueCollectionFactory
    ) {
        parent::__construct($context);
        $this->resultRawFactory = $resultRawFactory;
        $this->groupCollectionFactory = $groupCollectionFactory;
        $this->keyValueCollectionFactory = $keyValueCollectionFactory;
    }

    /**
     * Export action
     *
     * @return \Magento\Framework\Controller\Result\Raw
     */
    public function execute()
    {
        try {
            $format = $this->getRequest()->getParam('format', 'json');
            $format = strtolower($format);

            if (!in_array($format, ['json', 'csv'])) {
                $format = 'json';
            }

            $exportData = [
                'version' => '1.0',
                'exported_at' => date('Y-m-d H:i:s'),
                'groups' => [],
                'keyvalues' => []
            ];

            // Export Groups
            $groupCollection = $this->groupCollectionFactory->create();
            foreach ($groupCollection as $group) {
                $exportData['groups'][] = [
                    'name' => $group->getName(),
                    'code' => $group->getCode(),
                    'description' => $group->getDescription(),
                    'is_active' => (int)$group->getIsActive(),
                    'version' => $group->getVersion()
                ];
            }

            // Export Key-Value Pairs
            $keyValueCollection = $this->keyValueCollectionFactory->create();
            foreach ($keyValueCollection as $keyValue) {
                $exportData['keyvalues'][] = [
                    'group_code' => $this->getGroupCodeById($keyValue->getGroupId()),
                    'key_name' => $keyValue->getKeyName(),
                    'value' => $keyValue->getValue(),
                    'value_type' => $keyValue->getValueType(),
                    'file_path' => $keyValue->getFilePath(),
                    'is_active' => (int)$keyValue->getIsActive(),
                    'version' => $keyValue->getVersion()
                ];
            }

            $resultRaw = $this->resultRawFactory->create();
            $filename = 'appconfig_export_' . date('Y-m-d_His') . '.' . $format;

            if ($format === 'csv') {
                $csvData = $this->convertToCsv($exportData);
                $resultRaw->setHeader('Content-Type', 'text/csv; charset=utf-8');
                $resultRaw->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
                $resultRaw->setContents($csvData);
            } else {
                $jsonData = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $resultRaw->setHeader('Content-Type', 'application/json; charset=utf-8');
                $resultRaw->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
                $resultRaw->setContents($jsonData);
            }

            return $resultRaw;
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Export failed: %1', $e->getMessage()));
            $resultRedirect = $this->resultRedirectFactory->create();
            return $resultRedirect->setPath('appconfig/import');
        }
    }

    /**
     * Convert export data to CSV format
     *
     * @param array $exportData
     * @return string
     */
    protected function convertToCsv(array $exportData): string
    {
        $output = fopen('php://temp', 'r+');

        // Write Groups
        fputcsv($output, ['Type', 'Name', 'Code', 'Description', 'Is Active', 'Version', 'Key Name', 'Value', 'Value Type', 'File Path']);

        foreach ($exportData['groups'] as $group) {
            fputcsv($output, [
                'GROUP',
                $group['name'],
                $group['code'],
                $group['description'] ?? '',
                $group['is_active'],
                $group['version'] ?? '',
                '',
                '',
                '',
                ''
            ]);
        }

        // Write Key-Value Pairs
        foreach ($exportData['keyvalues'] as $keyValue) {
            fputcsv($output, [
                'KEYVALUE',
                '',
                $keyValue['group_code'] ?? '',
                '',
                $keyValue['is_active'],
                $keyValue['version'] ?? '',
                $keyValue['key_name'],
                $keyValue['value'] ?? '',
                $keyValue['value_type'] ?? 'text',
                $keyValue['file_path'] ?? ''
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        // Add BOM for UTF-8
        return "\xEF\xBB\xBF" . $csv;
    }

    /**
     * Get group code by group ID
     *
     * @param int|null $groupId
     * @return string|null
     */
    protected function getGroupCodeById($groupId)
    {
        if (!$groupId) {
            return null;
        }

        $groupCollection = $this->groupCollectionFactory->create();
        $group = $groupCollection->getItemById($groupId);

        return $group ? $group->getCode() : null;
    }
}


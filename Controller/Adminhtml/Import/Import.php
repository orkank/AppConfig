<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Controller\Adminhtml\Import;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\MediaStorage\Model\File\UploaderFactory;
use IDangerous\AppConfig\Model\GroupFactory;
use IDangerous\AppConfig\Model\KeyValueFactory;
use IDangerous\AppConfig\Model\ResourceModel\Group\CollectionFactory as GroupCollectionFactory;
use IDangerous\AppConfig\Model\ResourceModel\KeyValue\CollectionFactory as KeyValueCollectionFactory;
use Magento\Framework\Exception\LocalizedException;

class Import extends Action
{
    /**
     * Authorization level
     */
    const ADMIN_RESOURCE = 'IDangerous_AppConfig::import';

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var UploaderFactory
     */
    protected $uploaderFactory;

    /**
     * @var GroupFactory
     */
    protected $groupFactory;

    /**
     * @var KeyValueFactory
     */
    protected $keyValueFactory;

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
     * @param Filesystem $filesystem
     * @param UploaderFactory $uploaderFactory
     * @param GroupFactory $groupFactory
     * @param KeyValueFactory $keyValueFactory
     * @param GroupCollectionFactory $groupCollectionFactory
     * @param KeyValueCollectionFactory $keyValueCollectionFactory
     */
    public function __construct(
        Context $context,
        Filesystem $filesystem,
        UploaderFactory $uploaderFactory,
        GroupFactory $groupFactory,
        KeyValueFactory $keyValueFactory,
        GroupCollectionFactory $groupCollectionFactory,
        KeyValueCollectionFactory $keyValueCollectionFactory
    ) {
        parent::__construct($context);
        $this->filesystem = $filesystem;
        $this->uploaderFactory = $uploaderFactory;
        $this->groupFactory = $groupFactory;
        $this->keyValueFactory = $keyValueFactory;
        $this->groupCollectionFactory = $groupCollectionFactory;
        $this->keyValueCollectionFactory = $keyValueCollectionFactory;
    }

    /**
     * Import action
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('appconfig/import/index');

        try {
            if (empty($_FILES) || !isset($_FILES['import_file'])) {
                throw new LocalizedException(__('No file uploaded.'));
            }

            $fileFormat = $this->getRequest()->getParam('file_format', 'json');
            $importMode = $this->getRequest()->getParam('import_mode', 'append');

            $uploader = $this->uploaderFactory->create(['fileId' => 'import_file']);
            $uploader->setAllowedExtensions(['json', 'csv']);
            $uploader->setAllowRenameFiles(false);
            $uploader->setFilesDispersion(false);

            $mediaDirectory = $this->filesystem->getDirectoryRead(DirectoryList::VAR_DIR);
            $result = $uploader->save($mediaDirectory->getAbsolutePath('appconfig/import'));

            if (!$result['file']) {
                throw new LocalizedException(__('File upload failed.'));
            }

            $filePath = $mediaDirectory->getAbsolutePath('appconfig/import/' . $result['file']);
            $fileContent = file_get_contents($filePath);

            // Auto-detect format from file extension if not specified
            $fileExtension = strtolower(pathinfo($result['file'], PATHINFO_EXTENSION));
            if ($fileFormat === 'json' && $fileExtension === 'csv') {
                $fileFormat = 'csv';
            } elseif ($fileFormat === 'csv' && $fileExtension === 'json') {
                $fileFormat = 'json';
            }

            // Parse file content
            if ($fileFormat === 'csv') {
                $importData = $this->parseCsv($fileContent);
            } else {
                $importData = json_decode($fileContent, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new LocalizedException(__('Invalid JSON file: %1', json_last_error_msg()));
                }
            }

            if (!isset($importData['groups']) || !isset($importData['keyvalues'])) {
                throw new LocalizedException(__('Invalid import file format.'));
            }

            $groupsCreated = 0;
            $groupsUpdated = 0;
            $groupsDeleted = 0;
            $keyValuesCreated = 0;
            $keyValuesUpdated = 0;
            $keyValuesDeleted = 0;

            // If replace mode, delete all existing data
            if ($importMode === 'replace') {
                // Delete all key-values
                $keyValueCollection = $this->keyValueCollectionFactory->create();
                foreach ($keyValueCollection as $keyValue) {
                    $keyValue->delete();
                    $keyValuesDeleted++;
                }

                // Delete all groups
                $groupCollection = $this->groupCollectionFactory->create();
                foreach ($groupCollection as $group) {
                    $group->delete();
                    $groupsDeleted++;
                }
            }

            // Create mapping for group codes to IDs
            $groupCodeToId = [];
            $groupCollection = $this->groupCollectionFactory->create();
            foreach ($groupCollection as $group) {
                $groupCodeToId[$group->getCode()] = $group->getId();
            }

            // Import Groups
            foreach ($importData['groups'] as $groupData) {
                if (empty($groupData['code'])) {
                    continue;
                }

                $existingGroup = null;
                if (isset($groupCodeToId[$groupData['code']])) {
                    $existingGroup = $this->groupFactory->create()->load($groupCodeToId[$groupData['code']]);
                }

                if ($existingGroup && $existingGroup->getId()) {
                    // Update existing group
                    $existingGroup->setName($groupData['name'] ?? $existingGroup->getName());
                    $existingGroup->setDescription($groupData['description'] ?? $existingGroup->getDescription());
                    $existingGroup->setIsActive($groupData['is_active'] ?? $existingGroup->getIsActive());
                    $existingGroup->setVersion($groupData['version'] ?? $existingGroup->getVersion());
                    $existingGroup->save();
                    $groupsUpdated++;
                    $groupCodeToId[$groupData['code']] = $existingGroup->getId();
                } else {
                    // Create new group
                    $newGroup = $this->groupFactory->create();
                    $newGroup->setName($groupData['name'] ?? '');
                    $newGroup->setCode($groupData['code']);
                    $newGroup->setDescription($groupData['description'] ?? '');
                    $newGroup->setIsActive($groupData['is_active'] ?? 1);
                    $newGroup->setVersion($groupData['version'] ?? null);
                    $newGroup->save();
                    $groupsCreated++;
                    $groupCodeToId[$groupData['code']] = $newGroup->getId();
                }
            }

            // Import Key-Value Pairs
            foreach ($importData['keyvalues'] as $keyValueData) {
                if (empty($keyValueData['key_name'])) {
                    continue;
                }

                $groupId = null;
                if (!empty($keyValueData['group_code']) && isset($groupCodeToId[$keyValueData['group_code']])) {
                    $groupId = $groupCodeToId[$keyValueData['group_code']];
                }

                // Check if key-value already exists (by key_name and group_id)
                $keyValueCollection = $this->keyValueCollectionFactory->create();
                $keyValueCollection->addFieldToFilter('key_name', $keyValueData['key_name']);
                if ($groupId) {
                    $keyValueCollection->addFieldToFilter('group_id', $groupId);
                } else {
                    $keyValueCollection->addFieldToFilter('group_id', ['null' => true]);
                }

                $existingKeyValue = $keyValueCollection->getFirstItem();

                if ($existingKeyValue && $existingKeyValue->getId()) {
                    // Update existing key-value
                    $existingKeyValue->setGroupId($groupId);
                    $existingKeyValue->setValue($keyValueData['value'] ?? $existingKeyValue->getValue());
                    $existingKeyValue->setValueType($keyValueData['value_type'] ?? $existingKeyValue->getValueType());
                    $existingKeyValue->setFilePath($keyValueData['file_path'] ?? $existingKeyValue->getFilePath());
                    $existingKeyValue->setIsActive($keyValueData['is_active'] ?? $existingKeyValue->getIsActive());
                    $existingKeyValue->setVersion($keyValueData['version'] ?? $existingKeyValue->getVersion());
                    $existingKeyValue->save();
                    $keyValuesUpdated++;
                } else {
                    // Create new key-value
                    $newKeyValue = $this->keyValueFactory->create();
                    $newKeyValue->setGroupId($groupId);
                    $newKeyValue->setKeyName($keyValueData['key_name']);
                    $newKeyValue->setValue($keyValueData['value'] ?? '');
                    $newKeyValue->setValueType($keyValueData['value_type'] ?? 'text');
                    $newKeyValue->setFilePath($keyValueData['file_path'] ?? null);
                    $newKeyValue->setIsActive($keyValueData['is_active'] ?? 1);
                    $newKeyValue->setVersion($keyValueData['version'] ?? null);
                    $newKeyValue->save();
                    $keyValuesCreated++;
                }
            }

            // Clean up uploaded file
            @unlink($filePath);

            $message = __('Import completed successfully. ');
            if ($importMode === 'replace') {
                $message .= __('Deleted: %1 groups, %2 key-values. ', $groupsDeleted, $keyValuesDeleted);
            }
            $message .= __('Groups: %1 created, %2 updated. Key-Values: %3 created, %4 updated.',
                $groupsCreated,
                $groupsUpdated,
                $keyValuesCreated,
                $keyValuesUpdated
            );

            $this->messageManager->addSuccessMessage($message);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Import failed: %1', $e->getMessage()));
        }

        return $resultRedirect;
    }

    /**
     * Parse CSV file content to import data format
     *
     * @param string $csvContent
     * @return array
     */
    protected function parseCsv(string $csvContent): array
    {
        $importData = [
            'groups' => [],
            'keyvalues' => []
        ];

        // Remove BOM if present
        $csvContent = preg_replace('/^\xEF\xBB\xBF/', '', $csvContent);

        $lines = str_getcsv($csvContent, "\n");
        $header = null;

        foreach ($lines as $line) {
            $data = str_getcsv($line);

            if ($header === null) {
                $header = $data;
                continue;
            }

            if (count($data) < count($header)) {
                continue;
            }

            $row = array_combine($header, $data);

            if (isset($row['Type'])) {
                if ($row['Type'] === 'GROUP' && !empty($row['Code'])) {
                    $importData['groups'][] = [
                        'name' => $row['Name'] ?? '',
                        'code' => $row['Code'],
                        'description' => $row['Description'] ?? '',
                        'is_active' => isset($row['Is Active']) ? (int)$row['Is Active'] : 1,
                        'version' => $row['Version'] ?? null
                    ];
                } elseif ($row['Type'] === 'KEYVALUE' && !empty($row['Key Name'])) {
                    $importData['keyvalues'][] = [
                        'group_code' => !empty($row['Code']) ? $row['Code'] : null,
                        'key_name' => $row['Key Name'],
                        'value' => $row['Value'] ?? '',
                        'value_type' => $row['Value Type'] ?? 'text',
                        'file_path' => $row['File Path'] ?? null,
                        'is_active' => isset($row['Is Active']) ? (int)$row['Is Active'] : 1,
                        'version' => $row['Version'] ?? null
                    ];
                }
            }
        }

        return $importData;
    }
}


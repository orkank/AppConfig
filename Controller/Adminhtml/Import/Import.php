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

            $uploadedFilePath = $mediaDirectory->getAbsolutePath('appconfig/import/' . $result['file']);
            $fileContent = file_get_contents($uploadedFilePath);

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

            // Normalize import structure - support keyValues, key_values, Groups etc.
            $importData = $this->normalizeImportStructure($importData);

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

            // Create mapping for group codes to IDs (case-insensitive lookup)
            $groupCodeToId = [];
            $groupCollection = $this->groupCollectionFactory->create();
            foreach ($groupCollection as $group) {
                $groupCodeToId[$this->normalizeGroupCode($group->getCode())] = $group->getId();
            }

            // Import Groups - support both 'code' and 'Code'
            foreach ($importData['groups'] as $groupData) {
                $code = $groupData['code'] ?? $groupData['Code'] ?? '';
                if (empty($code)) {
                    continue;
                }

                $existingGroup = null;
                $codeKey = $this->normalizeGroupCode($code);
                if (isset($groupCodeToId[$codeKey])) {
                    $existingGroup = $this->groupFactory->create()->load($groupCodeToId[$codeKey]);
                }

                if ($existingGroup && $existingGroup->getId()) {
                    // Update existing group
                    $existingGroup->setName($groupData['name'] ?? $groupData['Name'] ?? $existingGroup->getName());
                    $existingGroup->setDescription($groupData['description'] ?? $groupData['Description'] ?? $existingGroup->getDescription());
                    $existingGroup->setIsActive($groupData['is_active'] ?? $groupData['Is Active'] ?? $existingGroup->getIsActive());
                    $existingGroup->setVersion($groupData['version'] ?? $groupData['Version'] ?? $existingGroup->getVersion());
                    $existingGroup->save();
                    $groupsUpdated++;
                    $groupCodeToId[$codeKey] = $existingGroup->getId();
                } else {
                    // Create new group
                    $newGroup = $this->groupFactory->create();
                    $newGroup->setName($groupData['name'] ?? $groupData['Name'] ?? '');
                    $newGroup->setCode($code);
                    $newGroup->setDescription($groupData['description'] ?? $groupData['Description'] ?? '');
                    $newGroup->setIsActive($groupData['is_active'] ?? $groupData['Is Active'] ?? 1);
                    $newGroup->setVersion($groupData['version'] ?? $groupData['Version'] ?? null);
                    $newGroup->save();
                    $groupsCreated++;
                    $groupCodeToId[$codeKey] = $newGroup->getId();
                }
            }

            // Import Key-Value Pairs
            foreach ($importData['keyvalues'] as $keyValueData) {
                $keyName = $keyValueData['key_name'] ?? $keyValueData['key'] ?? $keyValueData['Key Name'] ?? '';
                if (empty($keyName)) {
                    continue;
                }

                $groupCode = $keyValueData['group_code'] ?? $keyValueData['groupCode'] ?? $keyValueData['code'] ?? $keyValueData['Code'] ?? null;
                $groupId = null;
                if (!empty($groupCode) && isset($groupCodeToId[$this->normalizeGroupCode($groupCode)])) {
                    $groupId = $groupCodeToId[$this->normalizeGroupCode($groupCode)];
                } elseif (!empty($groupCode)) {
                    // Group doesn't exist - create it on the fly from keyvalue's group_code
                    $newGroup = $this->groupFactory->create();
                    $newGroup->setName($groupCode);
                    $newGroup->setCode($groupCode);
                    $newGroup->setDescription('');
                    $newGroup->setIsActive(1);
                    $newGroup->setVersion(null);
                    $newGroup->save();
                    $groupsCreated++;
                    $groupCodeToId[$this->normalizeGroupCode($groupCode)] = $newGroup->getId();
                    $groupId = $newGroup->getId();
                }

                // Check if key-value already exists (by key_name and group_id)
                $keyValueCollection = $this->keyValueCollectionFactory->create();
                $keyValueCollection->addFieldToFilter('key_name', $keyName);
                if ($groupId) {
                    $keyValueCollection->addFieldToFilter('group_id', $groupId);
                } else {
                    $keyValueCollection->addFieldToFilter('group_id', ['null' => true]);
                }

                $existingKeyValue = $keyValueCollection->getFirstItem();

                $valueType = $keyValueData['value_type'] ?? $keyValueData['Value Type'] ?? ($existingKeyValue->getId() ? $existingKeyValue->getValueType() : 'text');
                $rawValue = $keyValueData['value'] ?? $keyValueData['Value'] ?? '';

                // Map value to correct columns and normalize JSON strings (fix backslash/escaping issues)
                $typeSpecificData = $this->prepareImportValueByType($valueType, $rawValue, $keyValueData);

                $keyValueFilePath = $keyValueData['file_path'] ?? $keyValueData['File Path'] ?? null;
                if (empty($keyValueFilePath) && $valueType === 'file' && !empty($rawValue)) {
                    $keyValueFilePath = $rawValue; // Legacy: file path may be in value
                }

                $keyValueName = $keyValueData['name'] ?? $keyValueData['Name'] ?? $keyValueData['Admin Description'] ?? null;

                // Build complete model data - key_name and group_id must always be set
                $modelData = array_merge($typeSpecificData, [
                    'key_name' => (string)$keyName,
                    'group_id' => $groupId,
                    'file_path' => $keyValueFilePath ?? $keyValueData['file_path'] ?? null,
                    'is_active' => (int)($keyValueData['is_active'] ?? $keyValueData['Is Active'] ?? 1),
                    'version' => $keyValueData['version'] ?? $keyValueData['Version'] ?? null
                ]);
                if ($keyValueName !== null) {
                    $modelData['name'] = $keyValueName;
                }

                if ($existingKeyValue && $existingKeyValue->getId()) {
                    // Update existing key-value
                    $existingKeyValue->setData($modelData);
                    $existingKeyValue->save();
                    $keyValuesUpdated++;
                } else {
                    // Create new key-value
                    $newKeyValue = $this->keyValueFactory->create();
                    $newKeyValue->setData($modelData);
                    $newKeyValue->save();
                    $keyValuesCreated++;
                }
            }

            // Clean up uploaded file
            if (!empty($uploadedFilePath) && file_exists($uploadedFilePath)) {
                @unlink($uploadedFilePath);
            }

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
     * Normalize import structure - support various key names (keyValues, key_values, Groups, etc.)
     * and normalize each item to use key_name, group_code consistently
     *
     * @param array $data
     * @return array
     * @throws LocalizedException
     */
    protected function normalizeImportStructure(array $data): array
    {
        $groups = $data['groups'] ?? $data['Groups'] ?? $data['group'] ?? [];
        $keyvalues = $data['keyvalues'] ?? $data['keyValues'] ?? $data['key_values'] ?? [];

        if (empty($groups) && empty($keyvalues)) {
            throw new LocalizedException(__('Invalid import file format. Must contain groups or keyvalues.'));
        }

        // Ensure arrays
        $groups = is_array($groups) ? array_values($groups) : [];
        $keyvalues = is_array($keyvalues) ? array_values($keyvalues) : [];

        // Normalize each keyvalue item - map flexible keys to standard keys
        $normalizedKeyvalues = [];
        foreach ($keyvalues as $item) {
            if (!is_array($item)) {
                continue;
            }
            $normalized = [
                'key_name' => $item['key_name'] ?? $item['key'] ?? $item['keyName'] ?? $item['Key Name'] ?? '',
                'group_code' => $item['group_code'] ?? $item['groupCode'] ?? $item['code'] ?? $item['Code'] ?? null,
                'name' => $item['name'] ?? $item['Name'] ?? $item['Admin Description'] ?? null,
                'value' => $item['value'] ?? $item['Value'] ?? '',
                'value_type' => $item['value_type'] ?? $item['valueType'] ?? $item['Value Type'] ?? 'text',
                'file_path' => $item['file_path'] ?? $item['filePath'] ?? $item['File Path'] ?? null,
                'is_active' => $item['is_active'] ?? $item['isActive'] ?? $item['Is Active'] ?? 1,
                'version' => $item['version'] ?? $item['Version'] ?? null,
                'cms_include_content' => $item['cms_include_content'] ?? $item['cmsIncludeContent'] ?? null
            ];
            $normalizedKeyvalues[] = $normalized;
        }

        // Normalize each group item
        $normalizedGroups = [];
        foreach ($groups as $item) {
            if (!is_array($item)) {
                continue;
            }
            $code = $item['code'] ?? $item['Code'] ?? '';
            if (empty($code)) {
                continue;
            }
            $normalizedGroups[] = [
                'name' => $item['name'] ?? $item['Name'] ?? '',
                'code' => $code,
                'description' => $item['description'] ?? $item['Description'] ?? '',
                'is_active' => $item['is_active'] ?? $item['isActive'] ?? $item['Is Active'] ?? 1,
                'version' => $item['version'] ?? $item['Version'] ?? null
            ];
        }

        return [
            'groups' => $normalizedGroups,
            'keyvalues' => $normalizedKeyvalues
        ];
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
                    $value = $row['Value'] ?? '';
                    $valueType = $row['Value Type'] ?? 'text';
                    // Normalize JSON values from CSV (backslashes can get mangled)
                    if (in_array($valueType, ['products', 'json', 'category', 'categories', 'cms']) && !empty($value)) {
                        $value = $this->normalizeJsonString($value);
                    }
                    $kvItem = [
                        'group_code' => !empty($row['Code']) ? $row['Code'] : null,
                        'key_name' => $row['Key Name'],
                        'name' => $row['Admin Description'] ?? $row['Name'] ?? null,
                        'value' => $value,
                        'value_type' => $valueType,
                        'file_path' => $row['File Path'] ?? null,
                        'is_active' => isset($row['Is Active']) ? (int)$row['Is Active'] : 1,
                        'version' => $row['Version'] ?? null
                    ];
                    if (isset($row['CMS Include Content']) && $row['CMS Include Content'] !== '') {
                        $kvItem['cms_include_content'] = (int)$row['CMS Include Content'];
                    }
                    $importData['keyvalues'][] = $kvItem;
                }
            }
        }

        return $importData;
    }

    /**
     * Prepare import data by value type - maps to correct columns and normalizes JSON strings
     * Fixes backslash/escaping issues that break panel display
     *
     * @param string $valueType
     * @param string $rawValue
     * @param array $keyValueData
     * @return array Data to set on model (products_value, json_value, categories_value, text_value, value_type)
     */
    protected function prepareImportValueByType(string $valueType, string $rawValue, array $keyValueData): array
    {
        $result = [
            'value_type' => $valueType,
            'text_value' => '',
            'json_value' => '',
            'products_value' => '',
            'categories_value' => '',
            'cms_pages_value' => '',
            'cms_include_content' => 0,
            'value' => '' // deprecated, but keep for backward compat
        ];

        $normalizedJson = $this->normalizeJsonString($rawValue);

        switch ($valueType) {
            case 'products':
                $result['products_value'] = $normalizedJson;
                $result['value'] = $normalizedJson;
                break;

            case 'json':
                $result['json_value'] = $normalizedJson;
                $result['value'] = $normalizedJson;
                break;

            case 'category':
            case 'categories':
                $result['categories_value'] = $normalizedJson;
                $result['value'] = $normalizedJson;
                break;

            case 'cms':
                $result['cms_pages_value'] = $normalizedJson;
                $result['value'] = $normalizedJson;
                break;

            case 'file':
                // file_path is set separately by caller from keyValueData
                $result['value'] = $rawValue;
                break;

            case 'text':
            default:
                $result['text_value'] = $rawValue;
                $result['value'] = $rawValue;
                break;
        }

        return $result;
    }

    /**
     * Normalize JSON string - fix backslash/escaping issues from export/import round-trip
     * When JSON is embedded in JSON export, escaping can get corrupted
     *
     * @param string $jsonString
     * @return string Clean JSON string
     */
    protected function normalizeJsonString(string $jsonString): string
    {
        if (trim($jsonString) === '') {
            return '';
        }

        // Try direct decode first
        $decoded = json_decode($jsonString, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($decoded, JSON_UNESCAPED_UNICODE);
        }

        // Fix double-escaped backslashes (e.g. \" -> ")
        $fixed = stripslashes($jsonString);
        $decoded = json_decode($fixed, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($decoded, JSON_UNESCAPED_UNICODE);
        }

        // Return original if we can't fix it
        return $jsonString;
    }

    /**
     * Normalize group code for case-insensitive lookup
     *
     * @param string $code
     * @return string
     */
    protected function normalizeGroupCode(string $code): string
    {
        return strtolower(trim($code));
    }
}


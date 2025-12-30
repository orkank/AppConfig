<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;

/**
 * Migrate existing value_type based data to new column structure
 */
class MigrateValueTypeToColumns implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        $connection = $this->moduleDataSetup->getConnection();
        $tableName = $this->moduleDataSetup->getTable('idangerous_appconfig_keyvalue');

        // Check if new columns exist
        $columns = $connection->describeTable($tableName);
        if (!isset($columns['text_value'])) {
            // Columns don't exist yet, schema update will add them
            return $this;
        }

        // Migrate existing data based on value_type
        $select = $connection->select()
            ->from($tableName, ['keyvalue_id', 'value_type', 'value', 'file_path']);

        $rows = $connection->fetchAll($select);

        foreach ($rows as $row) {
            $updateData = [];
            $valueType = $row['value_type'] ?? 'text';
            $value = $row['value'] ?? '';
            $filePath = $row['file_path'] ?? '';

            switch ($valueType) {
                case 'text':
                    $updateData['text_value'] = $value;
                    break;

                case 'file':
                    // File path already in file_path column, just ensure it's set
                    if (empty($filePath) && !empty($value)) {
                        // Sometimes value might contain file path
                        $updateData['file_path'] = $value;
                    }
                    break;

                case 'json':
                    $updateData['json_value'] = $value;
                    break;

                case 'products':
                    $updateData['products_value'] = $value;
                    break;

                case 'category':
                    $updateData['categories_value'] = $value;
                    break;

                default:
                    // For unknown types, put in text_value
                    $updateData['text_value'] = $value;
                    break;
            }

            if (!empty($updateData)) {
                $connection->update(
                    $tableName,
                    $updateData,
                    ['keyvalue_id = ?' => $row['keyvalue_id']]
                );
            }
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }
}

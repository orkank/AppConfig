<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Model\ResourceModel\KeyValue;

use IDangerous\AppConfig\Model\KeyValue;
use IDangerous\AppConfig\Model\ResourceModel\KeyValue as KeyValueResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(KeyValue::class, KeyValueResource::class);
    }

    /**
     * Join with group table
     *
     * @return $this
     */
    public function joinGroup()
    {
        if (!$this->getFlag('group_joined')) {
            $this->getSelect()->joinLeft(
                ['group' => $this->getTable('idangerous_appconfig_group')],
                'main_table.group_id = group.group_id',
                ['group_name' => 'group.name', 'group_code' => 'group.code']
            );
            $this->setFlag('group_joined', true);
        }
        return $this;
    }
}



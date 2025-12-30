<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Model\ResourceModel\Group;

use IDangerous\AppConfig\Model\Group;
use IDangerous\AppConfig\Model\ResourceModel\Group as GroupResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(Group::class, GroupResource::class);
    }
}



<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Model;

use IDangerous\AppConfig\Model\ResourceModel\Group as GroupResource;
use Magento\Framework\Model\AbstractModel;

class Group extends AbstractModel
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(GroupResource::class);
    }
}



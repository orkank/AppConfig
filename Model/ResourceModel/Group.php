<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Group extends AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init('idangerous_appconfig_group', 'group_id');
    }
}



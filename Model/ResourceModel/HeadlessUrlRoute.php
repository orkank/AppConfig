<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class HeadlessUrlRoute extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('idangerous_appconfig_headless_route', 'route_id');
    }
}

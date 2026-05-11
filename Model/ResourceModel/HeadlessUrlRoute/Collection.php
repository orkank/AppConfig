<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Model\ResourceModel\HeadlessUrlRoute;

use IDangerous\AppConfig\Model\HeadlessUrlRoute;
use IDangerous\AppConfig\Model\ResourceModel\HeadlessUrlRoute as HeadlessUrlRouteResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(HeadlessUrlRoute::class, HeadlessUrlRouteResource::class);
    }
}

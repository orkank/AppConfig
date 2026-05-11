<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Model;

use IDangerous\AppConfig\Model\ResourceModel\HeadlessUrlRoute as HeadlessUrlRouteResource;
use Magento\Framework\Model\AbstractModel;

/**
 * Maps url_rewrite.entity_id rows to App Config key_name for headless routes.
 */
class HeadlessUrlRoute extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(HeadlessUrlRouteResource::class);
    }

    public function getAppconfigKey(): string
    {
        return (string) $this->getData('appconfig_key');
    }

    public function setAppconfigKey(string $key): self
    {
        return $this->setData('appconfig_key', $key);
    }
}

<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Model\Resolver;

use IDangerous\AppConfig\Model\HeadlessUrlRouteFactory;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * Field on EntityUrl (urlResolver query) when type is APPCONFIG_HEADLESS.
 */
class EntityUrlAppConfigKey implements ResolverInterface
{
    public function __construct(
        private readonly HeadlessUrlRouteFactory $headlessUrlRouteFactory
    ) {
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        if (($value['type'] ?? '') !== 'APPCONFIG_HEADLESS') {
            return null;
        }
        $id = isset($value['id']) ? (int) $value['id'] : 0;
        if ($id <= 0) {
            return null;
        }
        $route = $this->headlessUrlRouteFactory->create()->load($id);
        return (int) $route->getId() === $id ? $route->getAppconfigKey() : null;
    }
}

<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * Field on RoutableUrl (route query); value is injected by HeadlessUrlRouteDataProvider.
 */
class RoutableUrlAppConfigKey implements ResolverInterface
{
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
        return isset($value['app_config_headless_key']) ? (string) $value['app_config_headless_key'] : null;
    }
}

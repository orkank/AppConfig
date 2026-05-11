<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Model\GraphQl\Headless;

use IDangerous\AppConfig\Api\HeadlessIntegrationInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Webapi\Exception as WebapiException;

class RegisterHeadlessUrl implements ResolverInterface
{
    public function __construct(
        private readonly HeadlessIntegrationInterface $headlessIntegration
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
        $args ??= [];
        $payload = [
            'request_path' => isset($args['request_path']) ? (string) $args['request_path'] : '',
            'appconfig_key' => isset($args['appconfig_key']) ? (string) $args['appconfig_key'] : '',
        ];
        if (\array_key_exists('store_ids', $args) && $args['store_ids'] !== null) {
            $payload['store_ids'] = $args['store_ids'];
        }
        try {
            $out = $this->headlessIntegration->registerHeadlessUrl($payload);
            return [
                'route_id' => (int) ($out['route_id'] ?? 0),
                'request_path' => (string) ($out['request_path'] ?? ''),
                'appconfig_key' => (string) ($out['appconfig_key'] ?? ''),
                'store_ids' => \array_map('intval', $out['store_ids'] ?? []),
            ];
        } catch (WebapiException $e) {
            WebapiToGraphQl::rethrow($e);
        }
    }
}

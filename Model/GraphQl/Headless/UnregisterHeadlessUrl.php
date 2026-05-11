<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Model\GraphQl\Headless;

use IDangerous\AppConfig\Api\HeadlessIntegrationInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Webapi\Exception as WebapiException;

class UnregisterHeadlessUrl implements ResolverInterface
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
        $payload = ['route_id' => isset($args['route_id']) ? (int) $args['route_id'] : 0];
        if (\array_key_exists('store_ids', $args) && $args['store_ids'] !== null) {
            $payload['store_ids'] = $args['store_ids'];
        }
        try {
            $out = $this->headlessIntegration->unregisterHeadlessUrl($payload);

            return [
                'deleted_rewrites' => (int) ($out['deleted_rewrites'] ?? 0),
                'route_removed' => (bool) ($out['route_removed'] ?? false),
            ];
        } catch (WebapiException $e) {
            WebapiToGraphQl::rethrow($e);
        }
    }
}

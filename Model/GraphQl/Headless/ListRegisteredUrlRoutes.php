<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Model\GraphQl\Headless;

use IDangerous\AppConfig\Api\HeadlessIntegrationInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Webapi\Exception as WebapiException;

class ListRegisteredUrlRoutes implements ResolverInterface
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
        $storeId = null;
        if (isset($args['store_id']) && $args['store_id'] !== null && $args['store_id'] !== '') {
            $storeId = (int) $args['store_id'];
        }
        try {
            $out = $this->headlessIntegration->listHeadlessUrlRoutes($storeId);
            $items = $out['items'] ?? [];
            if (!\is_array($items)) {
                $items = [];
            }
            $rows = [];
            foreach ($items as $row) {
                if (!\is_array($row)) {
                    continue;
                }
                $rows[] = [
                    'route_id' => (int) ($row['route_id'] ?? 0),
                    'request_path' => (string) ($row['request_path'] ?? ''),
                    'appconfig_key' => (string) ($row['appconfig_key'] ?? ''),
                    'store_id' => (int) ($row['store_id'] ?? 0),
                ];
            }
            return ['items' => $rows];
        } catch (WebapiException $e) {
            WebapiToGraphQl::rethrow($e);
        }
    }
}

<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Model\GraphQl\Headless;

use IDangerous\AppConfig\Api\HeadlessIntegrationInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Webapi\Exception as WebapiException;

class SaveJsonItems implements ResolverInterface
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
        $items = $args['items'] ?? [];
        if (!\is_array($items)) {
            $items = [];
        }
        $payload = [];
        foreach ($items as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $payload[] = [
                'key' => isset($row['key']) ? (string) $row['key'] : '',
                'json' => $row['json'] ?? null,
            ];
        }
        try {
            $out = $this->headlessIntegration->saveJson($payload);
            return ['saved' => (int) ($out['saved'] ?? 0)];
        } catch (WebapiException $e) {
            WebapiToGraphQl::rethrow($e);
        }
    }
}

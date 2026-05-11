<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Model\GraphQl\Headless;

use IDangerous\AppConfig\Api\HeadlessIntegrationInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Webapi\Exception as WebapiException;

class DeleteHeadlessJsonKeys implements ResolverInterface
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
        $keys = $args['keys'] ?? [];
        if (!\is_array($keys)) {
            $keys = [];
        }
        try {
            $out = $this->headlessIntegration->deleteHeadlessJsonKeys(['keys' => $keys]);

            return ['deleted' => (int) ($out['deleted'] ?? 0)];
        } catch (WebapiException $e) {
            WebapiToGraphQl::rethrow($e);
        }
    }
}

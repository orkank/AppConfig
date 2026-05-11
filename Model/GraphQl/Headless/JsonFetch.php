<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Model\GraphQl\Headless;

use IDangerous\AppConfig\Api\HeadlessIntegrationInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Webapi\Exception as WebapiException;

class JsonFetch implements ResolverInterface
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
        $keys = isset($args['keys']) && \is_array($args['keys']) ? $args['keys'] : [];
        try {
            $out = $this->headlessIntegration->getJson($keys);
            $assoc = $out['items'] ?? [];
            if (!\is_array($assoc)) {
                $assoc = [];
            }
            $items = [];
            foreach ($assoc as $key => $json) {
                $items[] = [
                    'key' => (string) $key,
                    'json' => $json === null ? null : (string) $json,
                ];
            }
            return ['items' => $items];
        } catch (WebapiException $e) {
            WebapiToGraphQl::rethrow($e);
        }
    }
}

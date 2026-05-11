<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Model\GraphQl\Headless;

use IDangerous\AppConfig\Api\HeadlessIntegrationInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Webapi\Exception as WebapiException;

class ExchangeSession implements ResolverInterface
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
        try {
            $out = $this->headlessIntegration->exchangeSession(
                isset($args['delegationCode']) ? (string) $args['delegationCode'] : null
            );
            return [
                'session_token' => (string) $out['session_token'],
                'expires_at' => (string) $out['expires_at'],
                'expires_in' => (int) $out['expires_in'],
                'user_id' => (int) ($out['user_id'] ?? 0),
                'user_type' => (int) ($out['user_type'] ?? 0),
            ];
        } catch (WebapiException $e) {
            WebapiToGraphQl::rethrow($e);
        }
    }
}

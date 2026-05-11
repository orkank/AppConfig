<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Model\Headless;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Math\Random;
use Magento\Framework\Webapi\Exception as WebapiException;

/**
 * HMAC-signed session token for headless writes; minted only after admin delegation exchange.
 */
class SessionJwt
{
    private const ALLOWED_USER_TYPES = [
        UserContextInterface::USER_TYPE_ADMIN,
    ];

    public function __construct(
        private HeadlessConfig $headlessConfig,
        private Random $random
    ) {
    }

    /**
     * @throws WebapiException
     */
    public function mint(?int $storeId, int $userId, int $userType): array
    {
        if ($userId <= 0 || !\in_array($userType, self::ALLOWED_USER_TYPES, true)) {
            throw new WebapiException(
                __('Invalid session principal.'),
                0,
                WebapiException::HTTP_FORBIDDEN
            );
        }
        $ttl = $this->headlessConfig->getSessionTtl($storeId);
        $now = \time();

        $payload = [
            'typ' => 'appconfig_headless_session',
            'iat' => $now,
            'exp' => $now + $ttl,
            'jti' => $this->random->getRandomString(32),
            'sub' => $userId,
            'utt' => $userType,
        ];

        return [
            'token' => $this->encodePayload($payload, $storeId),
            'expires_at' => \gmdate('c', $now + $ttl),
            'expires_in' => $ttl,
        ];
    }

    /** @throws WebapiException */
    public function validate(string $jwt, ?int $storeId = null): void
    {
        $jwt = trim($jwt);
        if ($jwt === '') {
            throw new WebapiException(
                __('Invalid session token.'),
                0,
                WebapiException::HTTP_UNAUTHORIZED
            );
        }

        $parts = \explode('.', $jwt);
        if (\count($parts) !== 2) {
            throw new WebapiException(__('Invalid session token.'), 0, WebapiException::HTTP_UNAUTHORIZED);
        }
        [$payloadB64, $sig] = $parts;

        $expectedSig = \hash_hmac('sha256', $payloadB64, $this->signingKey($storeId), false);
        if (!hash_equals(\strtolower($expectedSig), \strtolower(\trim($sig)))) {
            throw new WebapiException(__('Invalid session token.'), 0, WebapiException::HTTP_UNAUTHORIZED);
        }

        $json = (string) \base64_decode($this->b64Pad($payloadB64), true);
        $data = json_decode($json, true);
        if (!is_array($data) || (($data['typ'] ?? '') !== 'appconfig_headless_session')) {
            throw new WebapiException(__('Invalid session token.'), 0, WebapiException::HTTP_UNAUTHORIZED);
        }

        $exp = (int) ($data['exp'] ?? 0);
        if ($exp < time()) {
            throw new WebapiException(__('Session token expired.'), 0, WebapiException::HTTP_UNAUTHORIZED);
        }

        $sub = (int) ($data['sub'] ?? 0);
        $utt = (int) ($data['utt'] ?? 0);
        if ($sub <= 0 || !\in_array($utt, self::ALLOWED_USER_TYPES, true)) {
            throw new WebapiException(__('Invalid session token.'), 0, WebapiException::HTTP_UNAUTHORIZED);
        }
    }

    private function encodePayload(array $payload, ?int $storeId): string
    {
        $json = \json_encode($payload, JSON_UNESCAPED_SLASHES);
        $payloadB64 = \rtrim(\strtr(\base64_encode($json ?: '{}'), '+/', '-_'), '=');

        return $payloadB64 . '.' .
            \hash_hmac('sha256', $payloadB64, $this->signingKey($storeId), false);
    }

    private function signingKey(?int $storeId): string
    {
        $secret = $this->headlessConfig->getSharedSecretPlain($storeId)
            ?: $this->headlessConfig->getSharedSecretPlain(null);
        return \hash('sha256', (string) $secret . '|appconfig_hs256_session', true);
    }

    private function b64Pad(string $b64Url): string
    {
        $b64 = strtr($b64Url, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad) {
            $b64 .= str_repeat('=', 4 - $pad);
        }
        return $b64;
    }
}

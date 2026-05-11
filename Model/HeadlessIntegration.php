<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Model;

use IDangerous\AppConfig\Api\HeadlessIntegrationInterface;
use IDangerous\AppConfig\Model\Headless\HeadlessConfig;
use IDangerous\AppConfig\Model\Headless\HmacVerifier;
use IDangerous\AppConfig\Model\Headless\JsonKeyValueSync;
use IDangerous\AppConfig\Model\Headless\SessionJwt;
use IDangerous\AppConfig\Model\Headless\DelegationManager;
use IDangerous\AppConfig\Model\UrlRewrite\HeadlessRouteManager;
use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Store\Model\StoreManagerInterface;

class HeadlessIntegration implements HeadlessIntegrationInterface
{
    public function __construct(
        private HmacVerifier $hmacVerifier,
        private SessionJwt $sessionJwt,
        private JsonKeyValueSync $jsonSync,
        private HeadlessConfig $headlessConfig,
        private StoreManagerInterface $storeManager,
        private HeadlessRouteManager $headlessRouteManager,
        private DelegationManager $delegationManager
    ) {
    }

    /**
     * @inheritdoc
     */
    public function exchangeSession(?string $delegationCode = null): array
    {
        $scoped = $this->storeScope();

        try {
            $this->hmacVerifier->verify(false, $scoped);
        } catch (WebapiException $e) {
            throw $e;
        }

        $delegation = \trim((string) ($delegationCode ?? ''));
        if ($delegation === '') {
            throw new WebapiException(
                __('delegationCode is required.'),
                0,
                WebapiException::HTTP_BAD_REQUEST
            );
        }

        $adminUserId = $this->delegationManager->consumePlainCode($delegation);
        if ($adminUserId === null) {
            throw new WebapiException(
                __('Invalid or expired delegation code.'),
                0,
                WebapiException::HTTP_UNAUTHORIZED
            );
        }

        $issued = $this->sessionJwt->mint($scoped, $adminUserId, UserContextInterface::USER_TYPE_ADMIN);

        return [
            'session_token' => $issued['token'],
            'expires_at' => $issued['expires_at'],
            'expires_in' => $issued['expires_in'],
            'user_id' => $adminUserId,
            'user_type' => UserContextInterface::USER_TYPE_ADMIN,
        ];
    }

    /**
     * Read-only: valid HMAC (shared secret) only; session JWT is not used.
     */
    public function getJson(array $keys): array
    {
        $scoped = $this->storeScope();
        try {
            $this->hmacVerifier->verify(false, $scoped);
        } catch (WebapiException $e) {
            throw $e;
        }

        $keysNorm = [];
        foreach ($keys as $k) {
            if ($k === null || $k === '') {
                continue;
            }
            $keysNorm[] = (string) $k;
        }
        if (!$keysNorm) {
            throw new WebapiException(
                __('Keys are required.'),
                0,
                WebapiException::HTTP_BAD_REQUEST
            );
        }

        return ['items' => $this->jsonSync->readKeys($keysNorm, $scoped)];
    }

    public function saveJson(array $items): array
    {
        $scoped = $this->storeScope();

        try {
            $jwt = $this->hmacVerifier->verify(true, $scoped);
            $this->sessionJwt->validate($jwt, $scoped);
        } catch (WebapiException $e) {
            throw $e;
        }

        if (!$this->headlessConfig->isWritesEnabled($scoped)) {
            throw new WebapiException(
                __('Writes are disabled for headless integration.'),
                0,
                WebapiException::HTTP_FORBIDDEN
            );
        }

        if (!$items) {
            throw new WebapiException(__('Items are required.'), 0, WebapiException::HTTP_BAD_REQUEST);
        }

        $saved = 0;
        foreach ($items as $row) {
            if (!\is_array($row)) {
                throw new WebapiException(__('Malformed payload.'), 0, WebapiException::HTTP_BAD_REQUEST);
            }
            $key = isset($row['key']) ? (string) $row['key'] : '';
            if ($key === '') {
                throw new WebapiException(__('Each item must contain a key.'), 0, WebapiException::HTTP_BAD_REQUEST);
            }
            if (!\array_key_exists('json', $row)) {
                throw new WebapiException(__('Each item must contain json payload.'), 0, WebapiException::HTTP_BAD_REQUEST);
            }
            $this->jsonSync->upsertJson($key, $row['json'], $scoped);
            ++$saved;
        }

        return ['saved' => $saved];
    }

    public function registerHeadlessUrl(array $payload): array
    {
        $scoped = $this->storeScope();

        try {
            $jwt = $this->hmacVerifier->verify(true, $scoped);
            $this->sessionJwt->validate($jwt, $scoped);
        } catch (WebapiException $e) {
            throw $e;
        }

        if (!$this->headlessConfig->isWritesEnabled($scoped)) {
            throw new WebapiException(
                __('Writes are disabled for headless integration.'),
                0,
                WebapiException::HTTP_FORBIDDEN
            );
        }

        $path = isset($payload['request_path']) ? trim((string) $payload['request_path']) : '';
        $key = isset($payload['appconfig_key']) ? trim((string) $payload['appconfig_key']) : '';
        if ($path === '' || $key === '') {
            throw new WebapiException(
                __('request_path and appconfig_key are required.'),
                0,
                WebapiException::HTTP_BAD_REQUEST
            );
        }

        $storeIds = null;
        if (\array_key_exists('store_ids', $payload)) {
            $raw = $payload['store_ids'];
            if ($raw !== null && !\is_array($raw)) {
                throw new WebapiException(
                    __('store_ids must be an array of store view IDs.'),
                    0,
                    WebapiException::HTTP_BAD_REQUEST
                );
            }
            $storeIds = $raw === null ? null : $raw;
        }

        return $this->headlessRouteManager->register($path, $key, $storeIds);
    }

    /**
     * Read-only catalogue of headless URL rewrites (same auth as getJson).
     */
    public function listHeadlessUrlRoutes(?int $storeId = null): array
    {
        $scoped = $this->storeScope();
        try {
            $this->hmacVerifier->verify(false, $scoped);
        } catch (WebapiException $e) {
            throw $e;
        }

        $filter = ($storeId !== null && $storeId > 0) ? $storeId : null;

        return ['items' => $this->headlessRouteManager->listRegisteredUrlRewrites($filter)];
    }

    /**
     * @inheritdoc
     */
    public function unregisterHeadlessUrl(array $payload): array
    {
        $scoped = $this->storeScope();

        try {
            $jwt = $this->hmacVerifier->verify(true, $scoped);
            $this->sessionJwt->validate($jwt, $scoped);
        } catch (WebapiException $e) {
            throw $e;
        }

        if (!$this->headlessConfig->isWritesEnabled($scoped)) {
            throw new WebapiException(
                __('Writes are disabled for headless integration.'),
                0,
                WebapiException::HTTP_FORBIDDEN
            );
        }

        $routeId = isset($payload['route_id']) ? (int) $payload['route_id'] : 0;
        $storeIds = null;
        if (\array_key_exists('store_ids', $payload)) {
            $raw = $payload['store_ids'];
            if ($raw !== null && !\is_array($raw)) {
                throw new WebapiException(
                    __('store_ids must be an array of store view IDs or null.'),
                    0,
                    WebapiException::HTTP_BAD_REQUEST
                );
            }
            $storeIds = $raw;
        }

        return $this->headlessRouteManager->unregister($routeId, $storeIds);
    }

    /**
     * @inheritdoc
     */
    public function deleteHeadlessJsonKeys(array $payload): array
    {
        $scoped = $this->storeScope();

        try {
            $jwt = $this->hmacVerifier->verify(true, $scoped);
            $this->sessionJwt->validate($jwt, $scoped);
        } catch (WebapiException $e) {
            throw $e;
        }

        if (!$this->headlessConfig->isWritesEnabled($scoped)) {
            throw new WebapiException(
                __('Writes are disabled for headless integration.'),
                0,
                WebapiException::HTTP_FORBIDDEN
            );
        }

        $keys = $payload['keys'] ?? null;
        if (!\is_array($keys)) {
            throw new WebapiException(__('keys array is required.'), 0, WebapiException::HTTP_BAD_REQUEST);
        }

        $deleted = $this->jsonSync->deleteHeadlessKeys($keys, $scoped);

        return ['deleted' => $deleted];
    }

    private function storeScope(): ?int
    {
        try {
            return (int) $this->storeManager->getStore()->getId();
        } catch (\Throwable $e) {
            return null;
        }
    }
}

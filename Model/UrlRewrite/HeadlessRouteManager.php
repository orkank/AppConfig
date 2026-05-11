<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Model\UrlRewrite;

use IDangerous\AppConfig\Model\HeadlessUrlRouteFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\Exception\UrlAlreadyExistsException;
use Magento\UrlRewrite\Model\ResourceModel\UrlRewrite as UrlRewriteResource;
use Magento\UrlRewrite\Model\ResourceModel\UrlRewriteCollectionFactory;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Model\UrlRewriteFactory;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite as UrlRewriteData;

/**
 * Persists headless storefront paths as url_rewrite rows for GraphQL urlResolver / route.
 */
class HeadlessRouteManager
{
    /**
     * Value stored in url_rewrite.entity_type (GraphQL reports APPCONFIG_HEADLESS).
     */
    public const ENTITY_TYPE = 'appconfig_headless';

    private const FRONT_NAME = 'idappcfg';

    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlRewriteFactory $urlRewriteFactory,
        private readonly UrlRewriteResource $urlRewriteResource,
        private readonly UrlRewriteCollectionFactory $urlRewriteCollectionFactory,
        private readonly UrlFinderInterface $urlFinder,
        private readonly HeadlessUrlRouteFactory $headlessUrlRouteFactory
    ) {
    }

    /**
     * @param int[]|null $storeIds null or empty => all storefront views
     * @return array{route_id:int,request_path:string,appconfig_key:string,store_ids:int[]}
     */
    public function register(string $requestPath, string $appconfigKey, ?array $storeIds): array
    {
        $normalizedPath = $this->normalizeRequestPath($requestPath);
        $keyTrim = trim($appconfigKey);
        if ($keyTrim === '') {
            throw new WebapiException(__('appconfig_key is required.'), 0, WebapiException::HTTP_BAD_REQUEST);
        }

        $effectiveStoreIds = $this->resolveStoreIds($storeIds);

        try {
            $routeId = $this->detectExistingRouteId($normalizedPath, $effectiveStoreIds);
            if ($routeId === null) {
                $route = $this->headlessUrlRouteFactory->create();
                $route->setAppconfigKey($keyTrim);
                $route->save();
                $routeId = (int) $route->getId();
            } else {
                $route = $this->headlessUrlRouteFactory->create()->load($routeId);
                if ((int) $route->getId() !== $routeId) {
                    throw new LocalizedException(__('Unable to load headless route.'));
                }
                if ($route->getAppconfigKey() !== $keyTrim) {
                    $route->setAppconfigKey($keyTrim);
                    $route->save();
                }
            }

            foreach ($effectiveStoreIds as $storeId) {
                $this->publishRouteOnStore((int) $storeId, $normalizedPath, $routeId);
            }

            return [
                'route_id' => $routeId,
                'request_path' => $normalizedPath,
                'appconfig_key' => $keyTrim,
                'store_ids' => $effectiveStoreIds,
            ];
        } catch (WebapiException $e) {
            throw $e;
        } catch (UrlAlreadyExistsException $e) {
            throw new WebapiException(
                __($e->getMessage()),
                0,
                WebapiException::HTTP_BAD_REQUEST
            );
        } catch (LocalizedException $e) {
            throw new WebapiException(__($e->getMessage()), 0, WebapiException::HTTP_BAD_REQUEST);
        }
    }

    private function publishRouteOnStore(int $storeId, string $requestPath, int $routeId): void
    {
        $atPath = $this->urlFinder->findOneByData(
            ['request_path' => $requestPath, 'store_id' => $storeId]
        );
        if ($atPath instanceof UrlRewriteData && $atPath->getUrlRewriteId()) {
            $type = $atPath->getEntityType();
            $eid = (int) $atPath->getEntityId();
            if ($type !== self::ENTITY_TYPE || $eid !== $routeId) {
                throw new WebapiException(
                    __(
                        'Another URL rewrite already uses request path "%1" for store %2.',
                        $requestPath,
                        $storeId
                    ),
                    0,
                    WebapiException::HTTP_BAD_REQUEST
                );
            }
            $this->refreshExistingRewrite((int) $atPath->getUrlRewriteId(), $routeId);
            return;
        }

        $this->deleteOurRewritesForEntityOnStore($routeId, $storeId);

        $urlRewrite = $this->urlRewriteFactory->create();
        $urlRewrite
            ->setEntityType(self::ENTITY_TYPE)
            ->setEntityId($routeId)
            ->setRequestPath($requestPath)
            ->setTargetPath($this->buildTargetPath($routeId))
            ->setRedirectType(0)
            ->setStoreId($storeId)
            ->setDescription(__('App Config headless route #%1', $routeId)->render())
            ->setIsAutogenerated(0);

        $this->urlRewriteResource->save($urlRewrite);
    }

    private function refreshExistingRewrite(int $urlRewriteId, int $routeId): void
    {
        $model = $this->urlRewriteFactory->create();
        $this->urlRewriteResource->load($model, $urlRewriteId);
        if (!$model->getId()) {
            return;
        }
        $desired = $this->buildTargetPath($routeId);
        if ($model->getTargetPath() !== $desired) {
            $model->setTargetPath($desired);
            $this->urlRewriteResource->save($model);
        }
    }

    private function deleteOurRewritesForEntityOnStore(int $routeId, int $storeId): void
    {
        $collection = $this->urlRewriteCollectionFactory->create();
        $collection
            ->addFieldToFilter('entity_type', self::ENTITY_TYPE)
            ->addFieldToFilter('entity_id', $routeId)
            ->addFieldToFilter('store_id', $storeId);
        foreach ($collection as $row) {
            $this->urlRewriteResource->delete($row);
        }
    }

    /**
     * All storefront rewrites created for headless routes (see register-url).
     *
     * @return list<array{route_id: int, request_path: string, appconfig_key: string, store_id: int}>
     */
    public function listRegisteredUrlRewrites(?int $storeId = null): array
    {
        $collection = $this->urlRewriteCollectionFactory->create();
        $collection->addFieldToFilter('entity_type', self::ENTITY_TYPE);
        if ($storeId !== null && $storeId > 0) {
            $collection->addFieldToFilter('store_id', $storeId);
        }
        $collection->setOrder('request_path', 'ASC');
        $collection->setOrder('store_id', 'ASC');

        $items = [];
        foreach ($collection as $ur) {
            $routeId = (int) $ur->getEntityId();
            if ($routeId <= 0) {
                continue;
            }
            $route = $this->headlessUrlRouteFactory->create()->load($routeId);
            if ((int) $route->getId() !== $routeId) {
                continue;
            }
            $items[] = [
                'route_id' => $routeId,
                'request_path' => (string) $ur->getRequestPath(),
                'appconfig_key' => $route->getAppconfigKey(),
                'store_id' => (int) $ur->getStoreId(),
            ];
        }

        return $items;
    }

    /**
     * Remove headless url_rewrite rows; delete the headless_route row when none remain.
     *
     * @param int[]|null $storeIds null = all stores (full route removal); non-empty list = those stores only
     * @return array{deleted_rewrites:int,route_removed:bool}
     */
    public function unregister(int $routeId, ?array $storeIds): array
    {
        if ($routeId <= 0) {
            throw new WebapiException(__('route_id is required.'), 0, WebapiException::HTTP_BAD_REQUEST);
        }

        $route = $this->headlessUrlRouteFactory->create()->load($routeId);
        if ((int) $route->getId() !== $routeId) {
            throw new WebapiException(__('Headless route not found.'), 0, WebapiException::HTTP_NOT_FOUND);
        }

        $collection = $this->urlRewriteCollectionFactory->create();
        $collection
            ->addFieldToFilter('entity_type', self::ENTITY_TYPE)
            ->addFieldToFilter('entity_id', $routeId);

        $deleted = 0;
        if ($storeIds === null) {
            foreach ($collection as $ur) {
                $this->urlRewriteResource->delete($ur);
                ++$deleted;
            }
            $route->delete();

            return ['deleted_rewrites' => $deleted, 'route_removed' => true];
        }

        $targetStores = \array_values(\array_unique(\array_filter(\array_map('intval', $storeIds), static fn (int $id): bool => $id > 0)));
        if (!$targetStores) {
            throw new WebapiException(
                __('store_ids cannot be empty. Omit store_ids to remove the entire route.'),
                0,
                WebapiException::HTTP_BAD_REQUEST
            );
        }

        foreach ($collection as $ur) {
            if (\in_array((int) $ur->getStoreId(), $targetStores, true)) {
                $this->urlRewriteResource->delete($ur);
                ++$deleted;
            }
        }

        $remaining = $this->urlRewriteCollectionFactory->create();
        $remaining
            ->addFieldToFilter('entity_type', self::ENTITY_TYPE)
            ->addFieldToFilter('entity_id', $routeId);
        $routeRemoved = false;
        if ($remaining->getSize() === 0) {
            $route->delete();
            $routeRemoved = true;
        }

        return ['deleted_rewrites' => $deleted, 'route_removed' => $routeRemoved];
    }

    private function detectExistingRouteId(string $normalizedPath, array $storeIds): ?int
    {
        foreach ($storeIds as $sid) {
            $existing = $this->urlFinder->findOneByData(
                ['request_path' => $normalizedPath, 'store_id' => (int) $sid]
            );
            if (!$existing instanceof UrlRewriteData || !$existing->getUrlRewriteId()) {
                continue;
            }
            if ($existing->getEntityType() !== self::ENTITY_TYPE) {
                continue;
            }
            $routeId = (int) $existing->getEntityId();
            return $routeId > 0 ? $routeId : null;
        }
        return null;
    }

    private function normalizeRequestPath(string $raw): string
    {
        $path = trim(str_replace('\\', '/', $raw));
        while (strpos($path, '//') !== false) {
            $path = str_replace('//', '/', $path);
        }
        $path = ltrim($path, '/');
        if ($path === '' || str_contains($path, '..')) {
            throw new WebapiException(__('Invalid request_path.'), 0, WebapiException::HTTP_BAD_REQUEST);
        }
        if ($path !== rawurldecode($path)) {
            throw new WebapiException(
                __('request_path must not be URL-encoded.'),
                0,
                WebapiException::HTTP_BAD_REQUEST
            );
        }
        if (\strlen($path) > 2048 || !preg_match('/^[A-Za-z0-9\\/\\-\\._]+$/', $path)) {
            throw new WebapiException(
                __('request_path contains invalid characters.'),
                0,
                WebapiException::HTTP_BAD_REQUEST
            );
        }
        return $path;
    }

    /**
     * @param int[]|null $requested
     * @return int[]
     */
    private function resolveStoreIds(?array $requested): array
    {
        $ids = [];
        if (!$requested) {
            foreach ($this->storeManager->getStores(false) as $store) {
                $ids[] = (int) $store->getId();
            }
            return $ids;
        }
        foreach ($requested as $id) {
            if ($id === null) {
                continue;
            }
            $sid = (int) $id;
            if ($sid > 0) {
                $ids[] = $sid;
            }
        }
        $ids = array_values(array_unique($ids));
        if (!$ids) {
            throw new WebapiException(
                __('store_ids must contain valid store views.'),
                0,
                WebapiException::HTTP_BAD_REQUEST
            );
        }
        return $ids;
    }

    private function buildTargetPath(int $routeId): string
    {
        return self::FRONT_NAME . '/route/index/route_id/' . $routeId;
    }
}

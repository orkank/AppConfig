<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Model\GraphQl\DataProvider;

use IDangerous\AppConfig\Model\HeadlessUrlRouteFactory;
use IDangerous\AppConfig\Model\UrlRewrite\HeadlessRouteManager;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\UrlRewriteGraphQl\Model\DataProvider\EntityDataProviderInterface;

/**
 * Supplies internal resolution data for the route GraphQL query (RoutableUrl).
 */
class HeadlessUrlRouteDataProvider implements EntityDataProviderInterface
{
    public function __construct(
        private readonly HeadlessUrlRouteFactory $headlessUrlRouteFactory
    ) {
    }

    /**
     * @inheritdoc
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getData(
        string $entity_type,
        int $id,
        ?ResolveInfo $info = null,
        ?int $storeId = null
    ): array {
        $route = $this->headlessUrlRouteFactory->create()->load($id);
        if ((int) $route->getId() !== $id) {
            throw new GraphQlNoSuchEntityException(__('This URL is not mapped to App Config headless data.'));
        }
        return [
            'type_id' => HeadlessRouteManager::ENTITY_TYPE,
            'app_config_headless_key' => $route->getAppconfigKey(),
        ];
    }
}

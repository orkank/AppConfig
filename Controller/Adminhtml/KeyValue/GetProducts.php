<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Controller\Adminhtml\KeyValue;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;

class GetProducts extends Action
{
    /**
     * Authorization level
     */
    const ADMIN_RESOURCE = 'IDangerous_AppConfig::keyvalues';

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var SearchCriteriaBuilderFactory
     */
    protected $searchCriteriaBuilderFactory;

    /**
     * @var FilterBuilder
     */
    protected $filterBuilder;

    /**
     * @var FilterGroupBuilder
     */
    protected $filterGroupBuilder;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param ProductRepositoryInterface $productRepository
     * @param SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        ProductRepositoryInterface $productRepository,
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        FilterBuilder $filterBuilder,
        FilterGroupBuilder $filterGroupBuilder
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
        $this->filterBuilder = $filterBuilder;
        $this->filterGroupBuilder = $filterGroupBuilder;
    }

    /**
     * Get products by IDs
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        try {
            $request = $this->getRequest();
            $productIds = $request->getParam('product_ids', []);

            // Handle array format: product_ids[0], product_ids[1], etc.
            if (empty($productIds) || !is_array($productIds)) {
                // Try to get from POST data directly
                $postData = $request->getPostValue();
                if (isset($postData['product_ids']) && is_array($postData['product_ids'])) {
                    $productIds = $postData['product_ids'];
                } elseif (isset($postData['product_ids']) && is_string($postData['product_ids'])) {
                    $productIds = [$postData['product_ids']];
                } else {
                    // Try to extract from request params
                    $allParams = $request->getParams();
                    $productIds = [];
                    foreach ($allParams as $key => $value) {
                        if (strpos($key, 'product_ids') === 0 || $key === 'product_ids') {
                            if (is_array($value)) {
                                $productIds = array_merge($productIds, $value);
                            } else {
                                $productIds[] = $value;
                            }
                        }
                    }
                }
            }

            // Filter and validate product IDs
            $productIds = array_filter(array_map('intval', $productIds));
            $productIds = array_unique($productIds);

            if (empty($productIds)) {
                return $resultJson->setData([
                    'success' => false,
                    'message' => __('No valid product IDs provided.')
                ]);
            }

            // Create a fresh SearchCriteriaBuilder instance to avoid conflicts
            $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();

            // Set page size to accommodate all products (no pagination limit)
            $searchCriteriaBuilder->setCurrentPage(1);
            $searchCriteriaBuilder->setPageSize(count($productIds) + 100);

            // Create filter using FilterBuilder for proper 'in' condition
            $filter = $this->filterBuilder
                ->setField('entity_id')
                ->setConditionType('in')
                ->setValue($productIds)
                ->create();

            // Create filter group and add to search criteria
            $filterGroup = $this->filterGroupBuilder
                ->addFilter($filter)
                ->create();

            $searchCriteriaBuilder->setFilterGroups([$filterGroup]);
            $searchCriteria = $searchCriteriaBuilder->create();

            // Get products
            $products = $this->productRepository->getList($searchCriteria);

            $productData = [];
            foreach ($products->getItems() as $product) {
                $productData[] = [
                    'id' => (int)$product->getId(),
                    'sku' => $product->getSku(),
                    'name' => $product->getName()
                ];
            }

            return $resultJson->setData([
                'success' => true,
                'products' => $productData
            ]);
        } catch (\Exception $e) {
            return $resultJson->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}


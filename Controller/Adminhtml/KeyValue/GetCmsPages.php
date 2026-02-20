<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Controller\Adminhtml\KeyValue;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;

class GetCmsPages extends Action
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
     * @var PageRepositoryInterface
     */
    protected $pageRepository;

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
     * @param PageRepositoryInterface $pageRepository
     * @param SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        PageRepositoryInterface $pageRepository,
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        FilterBuilder $filterBuilder,
        FilterGroupBuilder $filterGroupBuilder
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->pageRepository = $pageRepository;
        $this->searchCriteriaBuilderFactory = $searchCriteriaBuilderFactory;
        $this->filterBuilder = $filterBuilder;
        $this->filterGroupBuilder = $filterGroupBuilder;
    }

    /**
     * Get CMS pages by IDs
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $pageIds = $this->getRequest()->getParam('page_ids');

        if (empty($pageIds)) {
            $postData = $this->getRequest()->getPostValue();
            if (isset($postData['page_ids'])) {
                $pageIds = $postData['page_ids'];
            }
        }

        if (is_string($pageIds)) {
            $pageIds = strpos($pageIds, ',') !== false ? explode(',', $pageIds) : [$pageIds];
        }

        if (empty($pageIds) || !is_array($pageIds)) {
            return $result->setData([
                'success' => false,
                'message' => __('No CMS pages selected.')
            ]);
        }

        $pageIds = array_filter(array_map('intval', $pageIds));

        try {
            $searchCriteriaBuilder = $this->searchCriteriaBuilderFactory->create();
            $filter = $this->filterBuilder
                ->setField('page_id')
                ->setConditionType('in')
                ->setValue($pageIds)
                ->create();
            $filterGroup = $this->filterGroupBuilder->addFilter($filter)->create();
            $searchCriteriaBuilder->setFilterGroups([$filterGroup]);
            $searchCriteria = $searchCriteriaBuilder->create();

            $searchResult = $this->pageRepository->getList($searchCriteria);
            $pages = [];

            foreach ($searchResult->getItems() as $page) {
                $pages[] = [
                    'id' => (int)$page->getId(),
                    'title' => $page->getTitle(),
                    'identifier' => $page->getIdentifier(),
                    'update_time' => $page->getUpdateTime()
                ];
            }

            return $result->setData([
                'success' => true,
                'pages' => $pages
            ]);
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}

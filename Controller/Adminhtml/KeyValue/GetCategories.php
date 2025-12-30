<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Controller\Adminhtml\KeyValue;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;

class GetCategories extends Action
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
     * @var CollectionFactory
     */
    protected $categoryCollectionFactory;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param CollectionFactory $categoryCollectionFactory
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        CollectionFactory $categoryCollectionFactory
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $categoryIds = $this->getRequest()->getParam('category_ids');

        // Handle post data fallback checks
        if (empty($categoryIds)) {
             $postData = $this->getRequest()->getPostValue();
             if (isset($postData['category_ids'])) {
                 $categoryIds = $postData['category_ids'];
             }
        }

        // Handle string format (e.g. comma separated)
        if (is_string($categoryIds)) {
            if (strpos($categoryIds, ',') !== false) {
                $categoryIds = explode(',', $categoryIds);
            } else {
                $categoryIds = [$categoryIds];
            }
        }

        if (empty($categoryIds) || !is_array($categoryIds)) {
            return $result->setData([
                'success' => false,
                'message' => __('No categories selected.')
            ]);
        }

        // Sanitize
        $categoryIds = array_map('intval', $categoryIds);
        $categoryIds = array_filter($categoryIds);

        try {
            $collection = $this->categoryCollectionFactory->create();
            $collection->addAttributeToSelect('name')
                ->addFieldToFilter('entity_id', ['in' => $categoryIds]);

            $categories = [];
            foreach ($collection as $category) {
                $categories[] = [
                    'id' => $category->getId(),
                    'name' => $category->getName()
                ];
            }

            return $result->setData([
                'success' => true,
                'categories' => $categories
            ]);
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}

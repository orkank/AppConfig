<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Controller\Adminhtml\KeyValue;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class NewAction extends Action
{
    /**
     * Authorization level
     */
    const ADMIN_RESOURCE = 'IDangerous_AppConfig::keyvalues';

    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    /**
     * New action
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('IDangerous_AppConfig::keyvalues');
        $resultPage->addBreadcrumb(__('App Config'), __('App Config'));
        $resultPage->addBreadcrumb(__('Key-Value Pairs'), __('Key-Value Pairs'));
        $resultPage->addBreadcrumb(__('New Key-Value'), __('New Key-Value'));
        $resultPage->getConfig()->getTitle()->prepend(__('New Key-Value'));

        return $resultPage;
    }
}



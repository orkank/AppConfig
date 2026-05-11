<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Controller\Adminhtml\KeyValue;

use IDangerous\AppConfig\Model\ListOriginScope;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    /**
     * Authorization level
     */
    const ADMIN_RESOURCE = 'IDangerous_AppConfig::keyvalues';

    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /** @var \Magento\Backend\Model\Session */
    protected $backendSession;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param \Magento\Backend\Model\Session $backendSession
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        \Magento\Backend\Model\Session $backendSession
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->backendSession = $backendSession;
    }

    /**
     * Index action
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        $kvOrigin = $this->getRequest()->getParam('kv_origin');
        $allowed = [
            ListOriginScope::ADMIN_ONLY,
            ListOriginScope::HEADLESS_ONLY,
            ListOriginScope::ALL,
        ];
        if ($kvOrigin !== null && in_array($kvOrigin, $allowed, true)) {
            $this->backendSession->setData(ListOriginScope::SESSION_KEY, $kvOrigin);
        }

        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('IDangerous_AppConfig::keyvalues');
        $resultPage->addBreadcrumb(__('App Config'), __('App Config'));
        $resultPage->addBreadcrumb(__('Key-Value Pairs'), __('Key-Value Pairs'));
        $resultPage->getConfig()->getTitle()->prepend(__('Key-Value Pairs'));

        return $resultPage;
    }
}



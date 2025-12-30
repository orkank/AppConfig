<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Controller\Adminhtml\Group;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class NewAction extends Action
{
    /**
     * Authorization level
     */
    const ADMIN_RESOURCE = 'IDangerous_AppConfig::groups';

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
        $resultPage->setActiveMenu('IDangerous_AppConfig::groups');
        $resultPage->addBreadcrumb(__('App Config'), __('App Config'));
        $resultPage->addBreadcrumb(__('Groups'), __('Groups'));
        $resultPage->addBreadcrumb(__('New Group'), __('New Group'));
        $resultPage->getConfig()->getTitle()->prepend(__('New Group'));

        return $resultPage;
    }
}



<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Controller\Adminhtml\Group;

use IDangerous\AppConfig\Model\GroupFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action
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
     * @var GroupFactory
     */
    protected $groupFactory;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param GroupFactory $groupFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        GroupFactory $groupFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->groupFactory = $groupFactory;
    }

    /**
     * Edit action
     *
     * @return \Magento\Framework\View\Result\Page|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $id = (int) $this->getRequest()->getParam('group_id');
        $model = $this->groupFactory->create();

        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('This group no longer exists.'));
                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/');
            }
        }

        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('IDangerous_AppConfig::groups');
        $resultPage->addBreadcrumb(__('App Config'), __('App Config'));
        $resultPage->addBreadcrumb(__('Groups'), __('Groups'));
        $resultPage->addBreadcrumb(
            $id ? __('Edit Group') : __('New Group'),
            $id ? __('Edit Group') : __('New Group')
        );
        $resultPage->getConfig()->getTitle()->prepend(
            $id ? __('Edit Group') : __('New Group')
        );

        return $resultPage;
    }
}



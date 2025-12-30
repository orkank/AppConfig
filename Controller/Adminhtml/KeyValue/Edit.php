<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Controller\Adminhtml\KeyValue;

use IDangerous\AppConfig\Model\KeyValueFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Edit extends Action
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
     * @var KeyValueFactory
     */
    protected $keyValueFactory;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param KeyValueFactory $keyValueFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        KeyValueFactory $keyValueFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->keyValueFactory = $keyValueFactory;
    }

    /**
     * Edit action
     *
     * @return \Magento\Framework\View\Result\Page|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $id = (int) $this->getRequest()->getParam('keyvalue_id');
        $model = $this->keyValueFactory->create();

        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('This key-value pair no longer exists.'));
                $resultRedirect = $this->resultRedirectFactory->create();
                return $resultRedirect->setPath('*/*/');
            }
        }

        /** @var \Magento\Backend\Model\View\Result\Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('IDangerous_AppConfig::keyvalues');
        $resultPage->addBreadcrumb(__('App Config'), __('App Config'));
        $resultPage->addBreadcrumb(__('Key-Value Pairs'), __('Key-Value Pairs'));
        $resultPage->addBreadcrumb(
            $id ? __('Edit Key-Value') : __('New Key-Value'),
            $id ? __('Edit Key-Value') : __('New Key-Value')
        );
        $resultPage->getConfig()->getTitle()->prepend(
            $id ? __('Edit Key-Value') : __('New Key-Value')
        );

        return $resultPage;
    }
}



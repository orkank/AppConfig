<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Controller\Adminhtml\KeyValue;

use IDangerous\AppConfig\Model\KeyValueFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;

class Delete extends Action
{
    /**
     * Authorization level
     */
    const ADMIN_RESOURCE = 'IDangerous_AppConfig::keyvalues';

    /**
     * @var KeyValueFactory
     */
    protected $keyValueFactory;

    /**
     * @param Context $context
     * @param KeyValueFactory $keyValueFactory
     */
    public function __construct(
        Context $context,
        KeyValueFactory $keyValueFactory
    ) {
        parent::__construct($context);
        $this->keyValueFactory = $keyValueFactory;
    }

    /**
     * Delete action
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $id = (int) $this->getRequest()->getParam('keyvalue_id');

        if ($id) {
            try {
                $model = $this->keyValueFactory->create();
                $model->load($id);
                $model->delete();
                $this->messageManager->addSuccessMessage(__('You deleted the key-value pair.'));
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        }

        return $resultRedirect->setPath('*/*/');
    }
}



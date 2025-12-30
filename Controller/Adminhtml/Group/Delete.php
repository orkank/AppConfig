<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Controller\Adminhtml\Group;

use IDangerous\AppConfig\Model\GroupFactory;
use IDangerous\AppConfig\Model\KeyValueFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Exception\LocalizedException;

class Delete extends Action
{
    /**
     * Authorization level
     */
    const ADMIN_RESOURCE = 'IDangerous_AppConfig::groups';

    /**
     * @var GroupFactory
     */
    protected $groupFactory;

    /**
     * @var KeyValueFactory
     */
    protected $keyValueFactory;

    /**
     * @param Context $context
     * @param GroupFactory $groupFactory
     * @param KeyValueFactory $keyValueFactory
     */
    public function __construct(
        Context $context,
        GroupFactory $groupFactory,
        KeyValueFactory $keyValueFactory
    ) {
        parent::__construct($context);
        $this->groupFactory = $groupFactory;
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
        $id = (int) $this->getRequest()->getParam('group_id');

        if ($id) {
            try {
                $model = $this->groupFactory->create();
                $model->load($id);

                if (!$model->getId()) {
                    $this->messageManager->addErrorMessage(__('This group no longer exists.'));
                    return $resultRedirect->setPath('*/*/');
                }

                // Delete all associated key-value pairs first
                $keyValueCollection = $this->keyValueFactory->create()->getCollection();
                $keyValueCollection->addFieldToFilter('group_id', $id);

                $deletedCount = 0;
                foreach ($keyValueCollection as $keyValue) {
                    try {
                        $keyValue->delete();
                        $deletedCount++;
                    } catch (\Exception $e) {
                        // Log error but continue deleting other key-values
                        $this->_objectManager->get(\Psr\Log\LoggerInterface::class)->error(
                            'Error deleting key-value pair: ' . $e->getMessage()
                        );
                    }
                }

                // Delete the group
                $model->delete();

                if ($deletedCount > 0) {
                    $this->messageManager->addSuccessMessage(
                        __('You deleted the group and %1 associated key-value pair(s).', $deletedCount)
                    );
                } else {
                    $this->messageManager->addSuccessMessage(__('You deleted the group.'));
                }
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while deleting the group.'));
            }
        }

        return $resultRedirect->setPath('*/*/');
    }
}



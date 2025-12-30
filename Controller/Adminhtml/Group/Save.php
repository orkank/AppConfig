<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Controller\Adminhtml\Group;

use IDangerous\AppConfig\Model\GroupFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Exception\LocalizedException;

class Save extends Action
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
     * @param Context $context
     * @param GroupFactory $groupFactory
     */
    public function __construct(
        Context $context,
        GroupFactory $groupFactory
    ) {
        parent::__construct($context);
        $this->groupFactory = $groupFactory;
    }

    /**
     * Save action
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();

        if ($data) {
            // Handle UI Component form data structure
            if (isset($data['data']) && is_array($data['data'])) {
                $data = array_merge($data, $data['data']);
                unset($data['data']);
            }

            $id = (int) $this->getRequest()->getParam('group_id');
            if (empty($id) && isset($data['group_id'])) {
                $id = (int) $data['group_id'];
            }

            $model = $this->groupFactory->create();

            if ($id) {
                $model->load($id);
                if (!$model->getId()) {
                    $this->messageManager->addErrorMessage(__('This group no longer exists.'));
                    return $resultRedirect->setPath('*/*/');
                }
            }

            // Clean data - remove empty group_id for new records
            if (isset($data['group_id']) && empty($data['group_id'])) {
                unset($data['group_id']);
            }

            $model->setData($data);

            try {
                // Validate required fields
                if (empty($data['name'])) {
                    throw new LocalizedException(__('Name is required.'));
                }
                if (empty($data['code'])) {
                    throw new LocalizedException(__('Code is required.'));
                }

                $model->save();

                // Verify save was successful
                if (!$model->getId()) {
                    throw new LocalizedException(__('Failed to save the group. Model ID is missing after save.'));
                }

                $this->messageManager->addSuccessMessage(__('You saved the group.'));

                if ($this->getRequest()->getParam('back')) {
                    return $resultRedirect->setPath('*/*/edit', ['group_id' => $model->getId()]);
                }

                return $resultRedirect->setPath('*/*/');
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the group: %1', $e->getMessage()));
            }

            $this->_getSession()->setFormData($data);
            if ($id) {
                return $resultRedirect->setPath('*/*/edit', ['group_id' => $id]);
            }
            return $resultRedirect->setPath('*/*/new');
        }

        return $resultRedirect->setPath('*/*/');
    }
}



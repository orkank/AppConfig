<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Controller\Adminhtml\KeyValue;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;

class CmsGrid extends Action
{
    /**
     * Authorization level
     */
    const ADMIN_RESOURCE = 'IDangerous_AppConfig::keyvalues';

    /**
     * @param Context $context
     */
    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\Result\Layout
     */
    public function execute()
    {
        $resultLayout = $this->resultFactory->create(ResultFactory::TYPE_LAYOUT);
        return $resultLayout;
    }
}

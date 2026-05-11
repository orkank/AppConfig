<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Controller\Adminhtml\Headless;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

class Index extends AbstractHeadless implements HttpGetActionInterface
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    /**
     * @return ResultInterface|\Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        /** @var \Magento\Framework\View\Result\Page $page */
        $page = $this->resultPageFactory->create();
        $page->setActiveMenu('IDangerous_AppConfig::headless_integration');
        $page->addBreadcrumb(__('Headless Integration'), __('Headless Integration'));
        $page->getConfig()->getTitle()->prepend(__('Headless Integration'));
        return $page;
    }
}

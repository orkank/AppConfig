<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Controller\Adminhtml\Headless;

use IDangerous\AppConfig\Block\Adminhtml\Headless\LaunchPost;
use IDangerous\AppConfig\Model\Headless\DelegationManager;
use IDangerous\AppConfig\Model\Headless\HeadlessConfig;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Framework\App\Area;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\LayoutFactory;
use Magento\Framework\Controller\Result\RawFactory;

class Launch extends AbstractHeadless implements HttpGetActionInterface
{
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        private LayoutFactory $layoutFactory,
        private RawFactory $rawFactory,
        private DelegationManager $delegationManager,
        private HeadlessConfig $headlessConfig,
        private AdminSession $adminSession
    ) {
        parent::__construct($context);
    }

    /**
     * Returns an auto-submitting POST form so the delegation code is not placed in the access log query string.
     */
    public function execute(): ResultInterface
    {
        $resultRedirect = $this->resultRedirectFactory->create()->setPath('appconfig/headless/index');
        $base = $this->headlessConfig->getAppUrl();
        if ($base === '') {
            $this->messageManager->addErrorMessage(__('Set Headless app URL before launching.'));
            return $resultRedirect;
        }
        $user = $this->adminSession->getUser();
        $adminId = $user ? (int) $user->getId() : 0;
        if ($adminId <= 0) {
            $this->messageManager->addErrorMessage(__('Admin session required.'));
            return $resultRedirect;
        }

        $code = $this->delegationManager->mintForAdmin($adminId);
        $postUrl = $base . '/' . $this->headlessConfig->getDelegationPostPath();

        $layout = $this->layoutFactory->create([
            'area' => Area::AREA_ADMINHTML,
            'cacheable' => false,
        ]);
        /** @var LaunchPost $block */
        $block = $layout->createBlock(LaunchPost::class);
        $block->setTemplate('IDangerous_AppConfig::headless/launch_post.phtml');
        $block->setData('post_url', $postUrl);
        $block->setData('delegation_code', $code);

        return $this->rawFactory->create()
            ->setHeader('Content-Type', 'text/html; charset=UTF-8', true)
            ->setContents($block->toHtml());
    }
}

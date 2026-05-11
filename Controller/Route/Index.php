<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Controller\Route;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\ResultInterface;

/**
 * Placeholder target for headless url_rewrite rows; storefront may be served by Next.js instead.
 */
class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly ForwardFactory $forwardFactory
    ) {
    }

    public function execute(): ResultInterface
    {
        return $this->forwardFactory->create()->forward('noroute');
    }
}

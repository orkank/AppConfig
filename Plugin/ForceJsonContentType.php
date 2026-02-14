<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Plugin;

use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Webapi\Rest\Response\RendererFactory;
use Magento\Framework\ObjectManagerInterface;

class ForceJsonContentType
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @param Request $request
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(Request $request, ObjectManagerInterface $objectManager)
    {
        $this->request = $request;
        $this->objectManager = $objectManager;
    }

    /**
     * Force JSON renderer for appconfig endpoints
     *
     * @param RendererFactory $subject
     * @param callable $proceed
     * @return \Magento\Framework\Webapi\Rest\Response\RendererInterface
     */
    public function aroundGet(RendererFactory $subject, callable $proceed)
    {
        $pathInfo = $this->request->getPathInfo();

        // Force JSON renderer for appconfig endpoints
        if ($pathInfo && stripos($pathInfo, 'appconfig') !== false) {
            // Return JSON renderer directly
            return $this->objectManager->get(\Magento\Framework\Webapi\Rest\Response\Renderer\Json::class);
        }

        return $proceed();
    }
}

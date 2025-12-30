<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Plugin;

use Magento\Framework\Webapi\ServiceOutputProcessor;
use Magento\Framework\Webapi\Rest\Request;

class ServiceOutputProcessorPlugin
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Ensure appconfig endpoints return object instead of array
     *
     * @param ServiceOutputProcessor $subject
     * @param mixed $result
     * @param mixed $data
     * @param string $serviceClassName
     * @param string $serviceMethodName
     * @return mixed
     */
    public function afterProcess(ServiceOutputProcessor $subject, $result, $data, $serviceClassName, $serviceMethodName)
    {
        $pathInfo = $this->request->getPathInfo();

        // Only process appconfig endpoints
        if (strpos($pathInfo, '/appconfig/') === false) {
            return $result;
        }

        // If result is an array with DEFAULTS and GROUPS keys, convert to object
        if (is_array($result) && isset($result['DEFAULTS']) && isset($result['GROUPS'])) {
            // Convert to stdClass to ensure JSON object (not array)
            $object = new \stdClass();
            $object->DEFAULTS = $result['DEFAULTS'];
            $object->GROUPS = $result['GROUPS'];
            return $object;
        }

        return $result;
    }
}

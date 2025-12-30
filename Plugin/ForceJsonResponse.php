<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Plugin;

use Magento\Framework\Webapi\Rest\Request;

class ForceJsonResponse
{
    /**
     * Force JSON Accept header for appconfig endpoints
     *
     * @param Request $subject
     * @param array $result
     * @return array
     */
    public function afterGetAcceptTypes(Request $subject, array $result)
    {
        $pathInfo = $subject->getPathInfo();

        // Force JSON for appconfig endpoints
        if (strpos($pathInfo, '/appconfig/') !== false) {
            // Return JSON as preferred type
            return ['application/json'];
        }

        return $result;
    }
}

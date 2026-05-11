<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Model\GraphQl\Headless;

use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\Webapi\Exception as WebapiException;

/**
 * Maps WebapiException from HeadlessIntegration to GraphQL client errors.
 */
final class WebapiToGraphQl
{
    public static function rethrow(WebapiException $e): never
    {
        $status = $e->getHttpCode();
        $phrase = __($e->getMessage());

        if ($status === WebapiException::HTTP_BAD_REQUEST) {
            throw new GraphQlInputException($phrase);
        }

        if (\in_array(
            $status,
            [
                WebapiException::HTTP_UNAUTHORIZED,
                WebapiException::HTTP_FORBIDDEN,
                WebapiException::HTTP_NOT_FOUND,
            ],
            true
        )) {
            throw new GraphQlAuthorizationException($phrase);
        }

        throw new GraphQlInputException($phrase);
    }
}

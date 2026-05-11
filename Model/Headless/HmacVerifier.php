<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Model\Headless;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Framework\Webapi\Rest\Request as RestRequest;

/**
 * Verifies X-AppConfig-* HMAC headers against canonical payload.
 */
class HmacVerifier
{
    public function __construct(
        private HttpRequest $httpRequest,
        private RestRequest $restRequest,
        private HeadlessConfig $headlessConfig
    ) {
    }

    /**
     * @return string JWT when $expectSessionJwt is true
     * @throws WebapiException
     */
    public function verify(bool $expectSessionJwt, ?int $storeId = null): string
    {
        $secret = $this->headlessConfig->getSharedSecretPlain($storeId);
        if ($secret === null) {
            throw new WebapiException(
                __('Headless integration is not configured.'),
                0,
                WebapiException::HTTP_NOT_FOUND
            );
        }

        $tsRaw = $this->normalizeHeader('X-AppConfig-Timestamp');
        if ($tsRaw === '' || !ctype_digit($tsRaw)) {
            throw new WebapiException(
                __('Missing or invalid X-AppConfig-Timestamp.'),
                0,
                WebapiException::HTTP_UNAUTHORIZED
            );
        }

        $skew = $this->headlessConfig->getHmacSkew($storeId);
        $unix = (int) $tsRaw;
        if ($unix < (\time() - $skew) || $unix > (\time() + $skew)) {
            throw new WebapiException(
                __('Request timestamp outside allowed skew.'),
                0,
                WebapiException::HTTP_UNAUTHORIZED
            );
        }

        $provided = $this->normalizeHeader('X-AppConfig-Signature');
        if ($provided === '') {
            throw new WebapiException(
                __('Missing X-AppConfig-Signature.'),
                0,
                WebapiException::HTTP_UNAUTHORIZED
            );
        }

        $bodyRaw = (string) $this->httpRequest->getContent();
        if ($bodyRaw === '') {
            $params = $this->restRequest->getBodyParams();
            if (\is_array($params) && $params !== []) {
                $this->recursiveKsort($params);
                $bodyRaw = (string) \json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        $bodyHash = \hash('sha256', $bodyRaw);

        $pathInfo = $this->httpRequest->getOriginalPathInfo()
            ?: $this->httpRequest->getPathInfo()
            ?: '/';

        $method = \strtoupper($this->httpRequest->getMethod());
        $canonical = $method . "\n" . $pathInfo . "\n" . $tsRaw . "\n" . $bodyHash;
        $expected = \hash_hmac('sha256', $canonical, $secret);

        if (!\hash_equals($expected, $provided) && !\hash_equals($expected, \strtolower($provided))) {
            throw new WebapiException(
                __('Invalid signature.'),
                0,
                WebapiException::HTTP_UNAUTHORIZED
            );
        }

        if (!$expectSessionJwt) {
            return '';
        }

        $sessionJwt = \trim($this->normalizeHeader('X-AppConfig-Session'));
        if ($sessionJwt === '') {
            throw new WebapiException(
                __('Missing X-AppConfig-Session.'),
                0,
                WebapiException::HTTP_UNAUTHORIZED
            );
        }

        return $sessionJwt;
    }

    private function normalizeHeader(string $name): string
    {
        $v = $this->httpRequest->getHeader($name);
        if ($v === false) {
            return '';
        }
        if (\is_array($v)) {
            return \trim((string) ($v[0] ?? ''));
        }
        return \trim((string) $v);
    }

    private function recursiveKsort(array &$node): void
    {
        \ksort($node);
        foreach ($node as &$v) {
            if (\is_array($v)) {
                $this->recursiveKsort($v);
            }
        }
        unset($v);
    }
}

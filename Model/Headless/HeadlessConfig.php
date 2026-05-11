<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Model\Headless;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Typed getters for headless integration config paths (core_config_data).
 */
class HeadlessConfig
{
    private const XML_PATH_GROUP_CODE = 'appconfig/headless_integration/group_code';

    private const XML_PATH_KEY_PREFIX = 'appconfig/headless_integration/key_prefix';

    private const XML_PATH_APP_URL = 'appconfig/headless_integration/app_url';

    private const XML_PATH_SECRET = 'appconfig/headless_integration/shared_secret';

    private const XML_PATH_SESSION_TTL = 'appconfig/headless_integration/session_ttl';

    private const XML_PATH_HMAC_SKEW = 'appconfig/headless_integration/hmac_clock_skew';

    private const XML_PATH_DELEGATION_TTL = 'appconfig/headless_integration/delegation_ttl';

    private const XML_PATH_DELEGATION_POST_PATH = 'appconfig/headless_integration/delegation_post_path';

    private const XML_PATH_WRITES_ENABLED = 'appconfig/general/headless_writes_enabled';

    public function __construct(
        private ScopeConfigInterface $scopeConfig,
        private EncryptorInterface $encryptor
    ) {
    }

    public function hasValidSecret(?int $storeId = null): bool
    {
        return $this->getSharedSecretPlain($storeId) !== null;
    }

    /**
     * Headless credential paths are saved from admin as default scope — read the same scope to avoid
     * mismatches vs adminhtml store preview context ($storeId is ignored here by design).
     */
    public function getSharedSecretPlain(?int $storeId = null): ?string
    {
        $stored = trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_SECRET,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        ));

        if ($stored === '') {
            return null;
        }

        try {
            // Encrypted configs are stored ciphertext; decrypt if needed (Encryptor detects version).
            $secret = trim($this->encryptor->decrypt($stored));
        } catch (\Throwable $e) {
            // Treat as plaintext legacy or malformed
            $secret = trim($stored);
        }

        return $secret !== '' ? $secret : null;
    }

    public function getGroupCode(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_GROUP_CODE,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        ) ?: 'nextjs';
    }

    public function getKeyPrefix(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_KEY_PREFIX,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        ) ?: 'nextJS.';
    }

    public function getAppUrl(?int $storeId = null): string
    {
        return rtrim(trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_APP_URL,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        )), '/');
    }

    public function getSessionTtl(?int $storeId = null): int
    {
        return max(
            60,
            (int) $this->scopeConfig->getValue(
                self::XML_PATH_SESSION_TTL,
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT
            )
        );
    }

    public function getHmacSkew(?int $storeId = null): int
    {
        return max(
            60,
            (int) $this->scopeConfig->getValue(
                self::XML_PATH_HMAC_SKEW,
                ScopeConfigInterface::SCOPE_TYPE_DEFAULT
            )
        );
    }

    /**
     * One-time admin delegation code lifetime (seconds).
     */
    public function getDelegationTtlSeconds(?int $storeId = null): int
    {
        $v = (int) $this->scopeConfig->getValue(
            self::XML_PATH_DELEGATION_TTL,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );
        if ($v <= 0) {
            $v = 120;
        }

        return min(600, max(30, $v));
    }

    /**
     * Path on the headless origin for POST (leading slash optional), e.g. appconfig/delegation
     */
    public function getDelegationPostPath(?int $storeId = null): string
    {
        $p = \trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_DELEGATION_POST_PATH,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        ));
        if ($p === '') {
            return 'appconfig/delegation';
        }
        return \ltrim($p, '/');
    }

    public function isWritesEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_WRITES_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}

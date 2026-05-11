<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Model\Headless;

/**
 * Create and consume single-use admin delegation codes (POST redirect bootstrap).
 */
use IDangerous\AppConfig\Model\ResourceModel\HeadlessDelegation as HeadlessDelegationResource;

class DelegationManager
{
    public function __construct(
        private HeadlessConfig $headlessConfig,
        private HeadlessDelegationResource $delegationResource
    ) {
    }

    /**
     * Persists a hashed one-time code bound to the admin user; returns plaintext for the POST form only.
     */
    public function mintForAdmin(int $adminUserId): string
    {
        if ($adminUserId <= 0) {
            throw new \InvalidArgumentException('Invalid admin user id');
        }
        $this->delegationResource->pruneStale();
        $plain = \bin2hex(\random_bytes(32));
        $hash = \hash('sha256', $plain);
        $ttl = $this->headlessConfig->getDelegationTtlSeconds();
        $expires = (new \DateTimeImmutable('@' . (\time() + $ttl)))->setTimezone(new \DateTimeZone('UTC'));
        $this->delegationResource->insertNew($hash, $adminUserId, $expires->format('Y-m-d H:i:s'));

        return $plain;
    }

    /**
     * Marks code used and returns bound admin user ID, or null if invalid/expired/used.
     */
    public function consumePlainCode(string $plain): ?int
    {
        $plain = \trim($plain);
        if ($plain === '' || \strlen($plain) < 16) {
            return null;
        }
        $hash = \hash('sha256', $plain);

        return $this->delegationResource->consumeByCodeHash($hash);
    }
}

<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Block\Adminhtml\Headless;

use IDangerous\AppConfig\Model\Headless\HeadlessConfig;
use Magento\Backend\Block\Template;
use Magento\Store\Model\StoreManagerInterface;

class Settings extends Template
{
    protected $_template = 'IDangerous_AppConfig::headless/settings.phtml';

    public function __construct(
        Template\Context $context,
        private HeadlessConfig $headlessConfig,
        private StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getFormActionUrl(): string
    {
        return $this->getUrl('appconfig/headless/save');
    }

    public function getAppUrlStored(): string
    {
        try {
            return $this->headlessConfig->getAppUrl((int) $this->storeManager->getStore()->getId());
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function getGroupCodeStored(): string
    {
        try {
            return $this->headlessConfig->getGroupCode((int) $this->storeManager->getStore()->getId());
        } catch (\Throwable $e) {
            return 'nextjs';
        }
    }

    public function getKeyPrefixStored(): string
    {
        try {
            return $this->headlessConfig->getKeyPrefix((int) $this->storeManager->getStore()->getId());
        } catch (\Throwable $e) {
            return 'nextJS.';
        }
    }

    public function getSessionTtlStored(): int
    {
        try {
            return $this->headlessConfig->getSessionTtl((int) $this->storeManager->getStore()->getId());
        } catch (\Throwable $e) {
            return 3600;
        }
    }

    public function getHmacSkewStored(): int
    {
        try {
            return $this->headlessConfig->getHmacSkew((int) $this->storeManager->getStore()->getId());
        } catch (\Throwable $e) {
            return 300;
        }
    }

    public function isSecretConfigured(): bool
    {
        try {
            return $this->headlessConfig->getSharedSecretPlain((int) $this->storeManager->getStore()->getId()) !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getHeadlessAppPreviewUrl(): ?string
    {
        $base = $this->getAppUrlStored();

        return $base !== '' ? $base : null;
    }

    public function getDelegationLaunchUrl(): string
    {
        return $this->getUrl('appconfig/headless/launch');
    }

    public function getDelegationTtlStored(): int
    {
        try {
            return $this->headlessConfig->getDelegationTtlSeconds((int) $this->storeManager->getStore()->getId());
        } catch (\Throwable $e) {
            return 120;
        }
    }

    public function getDelegationPostPathStored(): string
    {
        try {
            return $this->headlessConfig->getDelegationPostPath((int) $this->storeManager->getStore()->getId());
        } catch (\Throwable $e) {
            return 'appconfig/delegation';
        }
    }
}

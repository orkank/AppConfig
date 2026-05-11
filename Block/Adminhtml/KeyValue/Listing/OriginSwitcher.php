<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Block\Adminhtml\KeyValue\Listing;

use IDangerous\AppConfig\Model\ListOriginScope;
use Magento\Backend\Block\Template;
use Magento\Backend\Model\Session;

class OriginSwitcher extends Template
{
    protected $_template = 'IDangerous_AppConfig::keyvalue/listing_origin_switcher.phtml';

    public function __construct(
        Template\Context $context,
        private Session $backendSession,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getActiveScope(): string
    {
        $v = $this->backendSession->getData(ListOriginScope::SESSION_KEY);
        return in_array($v, [ListOriginScope::ADMIN_ONLY, ListOriginScope::HEADLESS_ONLY, ListOriginScope::ALL], true)
            ? (string) $v
            : ListOriginScope::ADMIN_ONLY;
    }

    /** @return array<string,string> scope => URL */
    public function getUrls(): array
    {
        $scopes = [
            ListOriginScope::ADMIN_ONLY => (string) __('Admin entries only (default)'),
            ListOriginScope::HEADLESS_ONLY => (string) __('Headless / API writes only'),
            ListOriginScope::ALL => (string) __('All records'),
        ];
        $out = [];
        foreach ($scopes as $scope => $_label) {
            $out[$scope] = $this->getUrl('appconfig/keyvalue/index', ['kv_origin' => $scope]);
        }
        return $out;
    }
}

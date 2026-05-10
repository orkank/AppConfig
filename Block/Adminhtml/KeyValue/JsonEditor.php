<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Block\Adminhtml\KeyValue;

use Magento\Backend\Block\Template;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Supplies correct storefront media base URL for JSON editor (admin getUrl + URL_TYPE_MEDIA is unreliable).
 */
class JsonEditor extends Template
{
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        private StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getMediaBaseUrl(): string
    {
        $store = $this->storeManager->getDefaultStoreView();
        if ($store === null) {
            foreach ($this->storeManager->getStores() as $s) {
                return $s->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
            }
            return $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
        }

        return $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
    }

    public function getWysiwygImagesIndexUrl(): string
    {
        return $this->getUrl('cms/wysiwyg_images/index');
    }
}

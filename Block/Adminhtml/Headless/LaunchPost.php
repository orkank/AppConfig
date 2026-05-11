<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Block\Adminhtml\Headless;

use Magento\Backend\Block\Template;

class LaunchPost extends Template
{
    protected $_template = 'IDangerous_AppConfig::headless/launch_post.phtml';

    public function getPostUrl(): string
    {
        return (string) $this->getData('post_url');
    }

    public function getDelegationCode(): string
    {
        return (string) $this->getData('delegation_code');
    }
}

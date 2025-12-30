<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\UrlInterface;

class ApiInfo extends Field
{
    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param UrlInterface $urlBuilder
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        UrlInterface $urlBuilder,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Render API information
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $baseUrl = $this->urlBuilder->getBaseUrl(['_type' => UrlInterface::URL_TYPE_WEB]);
        $apiBaseUrl = rtrim($baseUrl, '/') . '/rest/V1/appconfig';

        $html = '<div style="background: #f8f8f8; border: 1px solid #ddd; padding: 15px; margin-top: 10px;">';
        $html .= '<h3 style="margin-top: 0;">API Endpoints</h3>';

        $html .= '<div style="margin-bottom: 15px;">';
        $html .= '<strong>Get Configuration Data:</strong><br/>';
        $html .= '<code style="background: #fff; padding: 5px 10px; display: inline-block; margin: 5px 0;">GET ' . $apiBaseUrl . '/config</code><br/>';
        $html .= '<div style="margin-top: 5px; color: #666; font-size: 12px;">';
        $html .= 'Parameters:<br/>';
        $html .= '&nbsp;&nbsp;- <code>appVersion</code> (optional): App version (e.g., "4.0.10+80")<br/>';
        $html .= '&nbsp;&nbsp;- <code>groupCode</code> (optional): Filter by group code<br/>';
        $html .= '</div>';
        $html .= '<div style="margin-top: 5px; color: #666; font-size: 12px;">';
        $html .= 'Example: <code>' . $apiBaseUrl . '/config?appVersion=4.0.10+80&amp;groupCode=mygroup</code>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '<div style="margin-bottom: 15px;">';
        $html .= '<strong>Get Groups:</strong><br/>';
        $html .= '<code style="background: #fff; padding: 5px 10px; display: inline-block; margin: 5px 0;">GET ' . $apiBaseUrl . '/groups</code><br/>';
        $html .= '<div style="margin-top: 5px; color: #666; font-size: 12px;">';
        $html .= 'Parameters:<br/>';
        $html .= '&nbsp;&nbsp;- <code>appVersion</code> (optional): App version (e.g., "4.0.10+80")<br/>';
        $html .= '</div>';
        $html .= '<div style="margin-top: 5px; color: #666; font-size: 12px;">';
        $html .= 'Example: <code>' . $apiBaseUrl . '/groups?appVersion=4.0.10+80</code>';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">';
        $html .= '<h4 style="margin-top: 0;">Version Control</h4>';
        $html .= '<div style="color: #666; font-size: 12px;">';
        $html .= 'Version format: <code>major.minor.patch+build</code> (e.g., 4.0.10+80)<br/>';
        $html .= 'If app version is provided, only configurations with matching or null versions will be returned.<br/>';
        $html .= 'If no version is specified in config, it will be returned for all app versions.';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">';
        $html .= '<h4 style="margin-top: 0;">Authentication</h4>';
        $html .= '<div style="color: #666; font-size: 12px;">';
        $html .= 'These endpoints use anonymous access. For production, consider adding authentication.';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }
}



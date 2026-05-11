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
        $html .= '<h4 style="margin-top: 0;">Authentication — public catalog endpoints</h4>';
        $html .= '<div style="color: #666; font-size: 12px;">';
        $html .= '<code>/config</code> and <code>/groups</code> remain anonymous. They never return Key-Value rows created by the headless API.';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">';
        $html .= '<h4 style="margin-top: 0;">Headless JSON API (HMAC; session only for writes)</h4>';
        $html .= '<div style="color: #666; font-size: 12px;">';
        $html .= 'Configure URL, shared HMAC secret, group/prefix/TTL/skew under <strong>Stores &gt; App Config &gt; Headless Integration</strong>.<br/>';
        $html .= 'Every call needs <code>X-AppConfig-Timestamp</code> and <code>X-AppConfig-Signature</code> (= HMAC-SHA256(secret, METHOD + "\\n" + PATH + "\\n" + TS + "\\n" + SHA256(raw body))). Writes also need <code>X-AppConfig-Session</code> (JWT from admin delegation exchange). Reads do <em>not</em> use session — secret + HMAC only.<br/>';
        $html .= 'If the shared secret is not saved, these routes return 404 and do not run.<br/><br/>';
        $html .= '<em>Note:</em> when Magento normalizes POST JSON into arrays, nested keys are recursively sorted prior to hashing so clients can reconstruct the canonical JSON.<br/><br/>';
        $html .= '<strong>POST</strong> <code>' . $apiBaseUrl . '/headless/session/exchange</code> — JSON <code>{"delegationCode":"…"}</code> only (one-time Admin Launch → Next) — HMAC, no <code>X-AppConfig-Session</code>. Returns write session JWT tied to that admin.<br/>';
        $html .= '<strong>GET</strong> <code>' . $apiBaseUrl . '/headless/json?keys[]=key1&amp;keys[]=key2</code> — HMAC only (no session; empty body ⇒ hash of empty string)<br/>';
        $html .= '<strong>POST</strong> <code>' . $apiBaseUrl . '/headless/json</code> — JSON upsert — HMAC + session JWT + “Allow Headless Writes”.<br/>';
        $html .= '<strong>POST</strong> <code>' . $apiBaseUrl . '/headless/register-url</code> — HMAC + session + writes; persisted <code>url_rewrite</code> rows surface in GraphQL <code>urlResolver</code> / <code>route</code> as <code>APPCONFIG_HEADLESS</code> — see <code>HEADLESS.md</code>.<br/>';
        $html .= '<strong>GraphQL</strong> (storefront <code>/graphql</code>): reading <code>appConfigHeadlessJson</code> is HMAC-only; exchange + write mutations need session after delegation — same <code>PATH</code> rule as REST (storefront <code>pathInfo</code>, not necessarily <code>/rest/.../V1/...</code>).';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }
}



<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Controller\Adminhtml\Headless;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\App\Cache\Type\Config as ConfigCacheType;
use Magento\Framework\App\Cache\TypeListInterface;

class Save extends AbstractHeadless implements HttpPostActionInterface
{
    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        private \Magento\Framework\App\Config\Storage\WriterInterface $configWriter,
        private EncryptorInterface $encryptor,
        private TypeListInterface $cacheTypeList
    ) {
        parent::__construct($context);
    }

    /**
     * @return ResultInterface|Redirect
     */
    public function execute()
    {
        /** @var Redirect $redirect */
        $redirect = $this->resultRedirectFactory->create();
        $redirect->setPath('appconfig/headless/index');

        if (!$this->getRequest()->isPost()) {
            return $redirect;
        }

        if (!$this->_formKeyValidator->validate($this->getRequest())) {
            $this->messageManager->addErrorMessage(__('Invalid form key.'));
            return $redirect;
        }

        $data = $this->getRequest()->getPostValue();
        if (!\is_array($data)) {
            $this->messageManager->addErrorMessage(__('Invalid form data.'));
            return $redirect;
        }

        $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;
        $scopeId = 0;

        $appUrl = isset($data['app_url']) ? trim((string) $data['app_url']) : '';
        $this->configWriter->save('appconfig/headless_integration/app_url', $appUrl, $scope, $scopeId);

        $groupCode = isset($data['group_code']) ? trim((string) $data['group_code']) : '';
        $this->configWriter->save('appconfig/headless_integration/group_code', $groupCode !== '' ? $groupCode : 'nextjs', $scope, $scopeId);

        $keyPrefix = isset($data['key_prefix']) ? trim((string) $data['key_prefix']) : '';
        $this->configWriter->save('appconfig/headless_integration/key_prefix', $keyPrefix !== '' ? $keyPrefix : 'nextJS.', $scope, $scopeId);

        $sessionTtl = isset($data['session_ttl']) ? max(60, (int) $data['session_ttl']) : 3600;
        $this->configWriter->save('appconfig/headless_integration/session_ttl', (string) $sessionTtl, $scope, $scopeId);

        $skew = isset($data['hmac_clock_skew']) ? max(60, (int) $data['hmac_clock_skew']) : 300;
        $this->configWriter->save('appconfig/headless_integration/hmac_clock_skew', (string) $skew, $scope, $scopeId);

        $delegationTtl = isset($data['delegation_ttl']) ? (int) $data['delegation_ttl'] : 120;
        $delegationTtl = min(600, max(30, $delegationTtl));
        $this->configWriter->save(
            'appconfig/headless_integration/delegation_ttl',
            (string) $delegationTtl,
            $scope,
            $scopeId
        );

        $delegPath = isset($data['delegation_post_path']) ? \trim((string) $data['delegation_post_path']) : '';
        $delegPath = \trim(\ltrim($delegPath, '/'));
        $this->configWriter->save(
            'appconfig/headless_integration/delegation_post_path',
            $delegPath !== '' ? $delegPath : 'appconfig/delegation',
            $scope,
            $scopeId
        );

        $secret = isset($data['shared_secret']) ? trim((string) $data['shared_secret']) : '';
        if ($secret !== '') {
            $this->configWriter->save(
                'appconfig/headless_integration/shared_secret',
                $this->encryptor->encrypt($secret),
                $scope,
                $scopeId
            );
        }

        $this->cacheTypeList->cleanType(ConfigCacheType::TYPE_IDENTIFIER);

        $this->messageManager->addSuccessMessage(__('Headless integration settings were saved.'));
        return $redirect;
    }
}

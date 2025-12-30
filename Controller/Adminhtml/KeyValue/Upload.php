<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Controller\Adminhtml\KeyValue;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use IDangerous\AppConfig\Model\File\UploaderFactory;

class Upload extends Action
{
    /**
     * Authorization level
     */
    const ADMIN_RESOURCE = 'IDangerous_AppConfig::keyvalues';

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var UploaderFactory
     */
    protected $uploaderFactory;

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param Filesystem $filesystem
     * @param UploaderFactory $uploaderFactory
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        Filesystem $filesystem,
        UploaderFactory $uploaderFactory
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->filesystem = $filesystem;
        $this->uploaderFactory = $uploaderFactory;
    }

    /**
     * Upload action
     *
     * @return \Magento\Framework\Controller\Result\Json
     */
    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        try {
            if (empty($_FILES)) {
                throw new \Exception('No file uploaded.');
            }

            $uploader = $this->uploaderFactory->create(['fileId' => 'file']);
            $uploader->setAllowedExtensions([
                // Image formats
                'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'bmp', 'ico', 'tiff', 'tif', 'heic', 'heif', 'avif',
                // Video formats
                'mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv', '3gp', 'ogv',
                // Office documents
                'doc', 'docx', 'pdf', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'rtf', 'csv',
                // Other
                'txt', 'zip', 'json', 'xml', 'rar', '7z'
            ]);
            $uploader->setAllowRenameFiles(true);
            $uploader->setFilesDispersion(true);

            $mediaDirectory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
            $result = $uploader->save($mediaDirectory->getAbsolutePath('appconfig/files'));

            if ($result['file']) {
                // Normalize file path - ensure result['file'] doesn't start with slash
                $filePath = ltrim($result['file'], '/');

                // Ensure the path starts with appconfig/files/ (uploader returns path relative to save directory)
                if (strpos($filePath, 'appconfig/files/') !== 0) {
                    $filePath = 'appconfig/files/' . $filePath;
                }

                // Get base media URL and remove trailing slash
                $mediaUrl = $this->_url->getBaseUrl(['_type' => \Magento\Framework\UrlInterface::URL_TYPE_MEDIA]);
                $mediaUrl = rtrim($mediaUrl, '/');

                $fileUrl = $mediaUrl . '/' . $filePath;

                $result['url'] = $fileUrl;
                $result['name'] = basename($filePath);
                $result['file_path'] = $filePath;
            }
        } catch (\Exception $e) {
            $result = [
                'error' => $e->getMessage(),
                'errorcode' => $e->getCode()
            ];
        }

        return $resultJson->setData($result);
    }
}



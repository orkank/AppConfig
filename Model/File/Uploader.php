<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Model\File;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\File\Mime;
use Magento\Framework\Validation\ValidationException;
use Magento\MediaStorage\Model\File\Uploader as MediaStorageUploader;
use Magento\MediaStorage\Model\File\Validator\Image;
use Magento\MediaStorage\Helper\File\Storage\Database;
use Magento\MediaStorage\Helper\File\Storage;
use Magento\MediaStorage\Model\File\Validator\NotProtectedExtension;

/**
 * Custom file uploader that allows all file types including SVG, videos, and office documents
 */
class Uploader extends MediaStorageUploader
{
    /**
     * Image validator instance
     *
     * @var Image
     */
    private $imageValidator;

    /**
     * Image MIME types that should be validated by Image validator
     * SVG is excluded because it cannot be validated with imagecreatefromstring()
     *
     * @var array
     */
    private $imageMimeTypesForValidation = [
        'image/png',
        'image/jpeg',
        'image/gif',
        'image/bmp',
        'image/vnd.microsoft.icon',
    ];

    /**
     * @param string $fileId
     * @param Database $coreFileStorageDb
     * @param Storage $coreFileStorage
     * @param NotProtectedExtension $validator
     */
    public function __construct(
        $fileId,
        Database $coreFileStorageDb,
        Storage $coreFileStorage,
        NotProtectedExtension $validator
    ) {
        parent::__construct($fileId, $coreFileStorageDb, $coreFileStorage, $validator);
    }

    /**
     * Check protected/allowed extension
     * Override to allow SVG and other file types that might be in protected list
     *
     * @param string $extension
     * @return boolean
     */
    public function checkAllowedExtension($extension)
    {
        $extension = strtolower($extension);

        // First check if extension is in our allowed list (Framework\File\Uploader check)
        $allowedExtensions = $this->_allowedExtensions ?? [];
        if (!empty($allowedExtensions) && !in_array($extension, $allowedExtensions)) {
            return false;
        }

        // For SVG and XML (which are in protected list), skip protected extension validator
        // These are in protected list for security, but we allow them for this module
        $safeExtensionsThatMayBeProtected = ['svg', 'xml'];

        if (in_array($extension, $safeExtensionsThatMayBeProtected)) {
            // Skip protected extension validator, directly return true if in allowed list
            // We already checked allowedExtensions above, so if we reach here, it's allowed
            return true;
        }

        // For other extensions, use normal validation with protected extension check
        // Validate with protected file types validator
        if (!$this->_validator->isValid($extension)) {
            return false;
        }

        // All checks passed
        return true;
    }

    /**
     * @inheritDoc
     */
    protected function _validateFile()
    {
        // Call Framework\File\Uploader's _validateFile() directly to skip MediaStorage's Image validator
        // This will only check file extension, not MIME type validation
        if ($this->_fileExists === false) {
            return;
        }

        // Check file extension (from Framework\File\Uploader)
        if (!$this->checkAllowedExtension($this->getFileExtension())) {
            throw new ValidationException(__('Disallowed file type.'));
        }

        // Run validate callbacks (from Framework\File\Uploader)
        foreach ($this->_validateCallbacks as $params) {
            if (is_object($params['object'])
                && method_exists($params['object'], $params['method'])
                && is_callable([$params['object'], $params['method']])
            ) {
                $params['object']->{$params['method']}($this->_file['tmp_name']);
            }
        }

        // Only validate with Image validator if it's a raster image format
        // Skip validation for SVG, videos, office documents, and other file types
        if ($this->shouldValidateAsImage()) {
            if (!$this->getImageValidator()->isValid($this->_file['tmp_name'])) {
                throw new ValidationException(__('File validation failed.'));
            }
        }
    }

    /**
     * Check if file should be validated as image
     *
     * @return bool
     */
    private function shouldValidateAsImage(): bool
    {
        if (!isset($this->_file['tmp_name']) || !file_exists($this->_file['tmp_name'])) {
            return false;
        }

        try {
            $mime = ObjectManager::getInstance()->get(Mime::class);
            $mimeType = $mime->getMimeType($this->_file['tmp_name']);
            return in_array($mimeType, $this->imageMimeTypesForValidation, true);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Return image validator class.
     *
     * @return Image
     */
    private function getImageValidator(): Image
    {
        if (!$this->imageValidator) {
            $this->imageValidator = ObjectManager::getInstance()->get(Image::class);
        }

        return $this->imageValidator;
    }
}

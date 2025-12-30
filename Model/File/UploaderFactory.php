<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Model\File;

use Magento\Framework\ObjectManagerInterface;
use Magento\MediaStorage\Helper\File\Storage\Database;
use Magento\MediaStorage\Helper\File\Storage;
use Magento\MediaStorage\Model\File\Validator\NotProtectedExtension;

/**
 * Factory for creating custom Uploader instances
 */
class UploaderFactory
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * Create uploader instance
     *
     * @param array $data
     * @return Uploader
     */
    public function create(array $data = [])
    {
        $fileId = $data['fileId'] ?? 'file';
        $coreFileStorageDb = $this->objectManager->get(Database::class);
        $coreFileStorage = $this->objectManager->get(Storage::class);
        $validator = $this->objectManager->get(NotProtectedExtension::class);

        return $this->objectManager->create(
            Uploader::class,
            [
                'fileId' => $fileId,
                'coreFileStorageDb' => $coreFileStorageDb,
                'coreFileStorage' => $coreFileStorage,
                'validator' => $validator
            ]
        );
    }
}

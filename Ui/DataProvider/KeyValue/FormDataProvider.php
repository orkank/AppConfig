<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Ui\DataProvider\KeyValue;

use IDangerous\AppConfig\Model\ResourceModel\KeyValue\CollectionFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

class FormDataProvider extends AbstractDataProvider
{
    /**
     * @var DataPersistorInterface
     */
    protected $dataPersistor;

    /**
     * @var array
     */
    protected $loadedData;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param DataPersistorInterface $dataPersistor
     * @param StoreManagerInterface $storeManager
     * @param Filesystem $filesystem
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        DataPersistorInterface $dataPersistor,
        StoreManagerInterface $storeManager,
        Filesystem $filesystem,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        $this->dataPersistor = $dataPersistor;
        $this->storeManager = $storeManager;
        $this->filesystem = $filesystem;
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * Get data
     *
     * @return array
     */
    public function getData()
    {
        if (isset($this->loadedData)) {
            return $this->loadedData;
        }

        $items = $this->collection->getItems();
        foreach ($items as $item) {
            $data = $item->getData();

            // Prepare file data for UI component if file_path exists
            if ($item->getFilePath()) {
                $filePath = $item->getFilePath();
                // Normalize file path - ensure it doesn't have leading slash but preserve appconfig/files/ prefix
                $filePath = ltrim($filePath, '/');

                // Ensure the path starts with appconfig/files/
                if (strpos($filePath, 'appconfig/files/') !== 0) {
                    // If missing prefix, add it (shouldn't happen, but safety check)
                    $filePath = 'appconfig/files/' . ltrim($filePath, '/');
                }

                $mediaUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
                // Remove trailing slash from media URL if exists
                $mediaUrl = rtrim($mediaUrl, '/');

                // Get file size if file exists
                $fileSize = 0;
                try {
                    $mediaDirectory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
                    // Check if file exists using the full path relative to media directory
                    if ($mediaDirectory->isFile($filePath)) {
                        $fileSize = $mediaDirectory->stat($filePath)['size'] ?? 0;
                    }
                } catch (\Exception $e) {
                    // File doesn't exist or can't be accessed, keep size as 0
                }

                $data['file'] = [
                    [
                        'file' => $filePath, // Store full path including appconfig/files/ prefix
                        'name' => basename($filePath),
                        'url' => $mediaUrl . '/' . $filePath, // Correct concatenation with full path
                        'size' => $fileSize
                    ]
                ];
            }

            // Map products_value to selected_products for form
            if ($item->getProductsValue()) {
                $data['selected_products'] = $item->getProductsValue();
            }

            // Map categories_value to selected_categories for form
            if ($item->getCategoriesValue()) {
                $data['selected_categories'] = $item->getCategoriesValue();
            }

            // Map cms_pages_value to selected_cms_pages for form
            if ($item->getCmsPagesValue()) {
                $data['selected_cms_pages'] = $item->getCmsPagesValue();
            }

            // Ensure all new columns are present
            $data['text_value'] = $item->getTextValue() ?? '';
            $data['json_value'] = $item->getJsonValue() ?? '';
            $data['products_value'] = $item->getProductsValue() ?? '';
            $data['categories_value'] = $item->getCategoriesValue() ?? '';
            $data['cms_pages_value'] = $item->getCmsPagesValue() ?? '';
            $data['cms_include_content'] = (int) ($item->getData('cms_include_content') ?? 0);

            $this->loadedData[$item->getId()] = $data;
        }

        $data = $this->dataPersistor->get('appconfig_keyvalue');
        if (!empty($data)) {
            $item = $this->collection->getNewEmptyItem();
            $item->setData($data);
            $this->loadedData[$item->getId()] = $item->getData();
            $this->dataPersistor->clear('appconfig_keyvalue');
        }

        return $this->loadedData;
    }
}



<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Controller\Adminhtml\KeyValue;

use IDangerous\AppConfig\Model\KeyValueFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use IDangerous\AppConfig\Model\File\UploaderFactory;

class Save extends Action
{
    /**
     * Authorization level
     */
    const ADMIN_RESOURCE = 'IDangerous_AppConfig::keyvalues';

    /**
     * @var KeyValueFactory
     */
    protected $keyValueFactory;

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
     * @param KeyValueFactory $keyValueFactory
     * @param Filesystem $filesystem
     * @param UploaderFactory $uploaderFactory
     */
    public function __construct(
        Context $context,
        KeyValueFactory $keyValueFactory,
        Filesystem $filesystem,
        UploaderFactory $uploaderFactory
    ) {
        parent::__construct($context);
        $this->keyValueFactory = $keyValueFactory;
        $this->filesystem = $filesystem;
        $this->uploaderFactory = $uploaderFactory;
    }

    /**
     * Save action
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();

        if ($data) {
            // Handle UI Component form data structure
            if (isset($data['data']) && is_array($data['data'])) {
                $data = array_merge($data, $data['data']);
                unset($data['data']);
            }

            $id = (int) $this->getRequest()->getParam('keyvalue_id');
            if (empty($id) && isset($data['keyvalue_id'])) {
                $id = (int) $data['keyvalue_id'];
            }

            $model = $this->keyValueFactory->create();

            if ($id) {
                $model->load($id);
                if (!$model->getId()) {
                    $this->messageManager->addErrorMessage(__('This key-value pair no longer exists.'));
                    return $resultRedirect->setPath('*/*/');
                }
            }

            // Clean data - remove empty keyvalue_id for new records
            if (isset($data['keyvalue_id']) && empty($data['keyvalue_id'])) {
                unset($data['keyvalue_id']);
            }

            // Handle group_id - convert empty values to NULL (group is optional)
            if (isset($data['group_id'])) {
                $groupId = trim((string)$data['group_id']);
                if ($groupId === '' || $groupId === '0' || (int)$groupId === 0) {
                    $data['group_id'] = null;
                } else {
                    $data['group_id'] = (int)$groupId;
                }
            } else {
                $data['group_id'] = null;
            }

            // Handle file upload
            // First, check if file was deleted (empty array or file_path is explicitly empty)
            $fileDeleted = false;
            if (isset($data['file']) && is_array($data['file']) && empty($data['file'])) {
                // File deletion requested - UI Component sends empty array [] when file is deleted
                $fileDeleted = true;
            } elseif (isset($data['file']['delete']) && $data['file']['delete']) {
                // File deletion requested (legacy format)
                $fileDeleted = true;
            } elseif (isset($data['file_path']) && (trim($data['file_path']) === '' || $data['file_path'] === null)) {
                // file_path is explicitly empty string - file deletion requested
                // This handles the case where file_path is sent as empty string in payload
                $fileDeleted = true;
            }

            // If file was deleted, clear file_path and skip other file handling
            if ($fileDeleted) {
                $data['file_path'] = '';
            } elseif (isset($_FILES['file']) && !empty($_FILES['file']['name'])) {
                // New file uploaded
                try {
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
                        $data['file_path'] = 'appconfig/files/' . $filePath;
                    }
                } catch (\Exception $e) {
                    $this->messageManager->addErrorMessage(__('File upload failed: %1', $e->getMessage()));
                }
            } elseif (isset($data['file'][0]['file']) && !empty($data['file'][0]['file']) && !isset($_FILES['file']['name'])) {
                // Keep existing file - use the file_path from form data, but ensure it has the correct prefix
                $existingFilePath = $data['file'][0]['file'];
                // Normalize the path - ensure it starts with appconfig/files/
                $existingFilePath = ltrim($existingFilePath, '/');
                if (strpos($existingFilePath, 'appconfig/files/') !== 0) {
                    // If the path doesn't start with appconfig/files/, check if model has the correct path
                    if ($id && $model->getFilePath()) {
                        $existingFilePath = $model->getFilePath();
                    } else {
                        // Try to reconstruct the path
                        $existingFilePath = 'appconfig/files/' . ltrim($existingFilePath, '/');
                    }
                }
                $data['file_path'] = $existingFilePath;
            } elseif (!isset($data['file']) && $id) {
                // File field not present in form data
                // If file_path is explicitly set to empty string, delete the file
                if (isset($data['file_path']) && (trim($data['file_path']) === '' || $data['file_path'] === null)) {
                    // file_path is explicitly empty - delete file
                    $data['file_path'] = '';
                } elseif (isset($data['file_path']) && !empty(trim($data['file_path']))) {
                    // file_path has a value - keep it
                    $data['file_path'] = trim($data['file_path']);
                } elseif ($model->getFilePath()) {
                    // No file_path in data, keep existing file
                    $data['file_path'] = $model->getFilePath();
                } else {
                    $data['file_path'] = '';
                }
            } else {
                // No file data provided, set to empty
                $data['file_path'] = '';
            }

            // Final safety check: if file_path is explicitly empty string, ensure it's cleared
            // This handles the case where file_path is sent as empty string in payload
            // This check must be AFTER all other file handling logic
            if (isset($data['file_path']) && (trim($data['file_path']) === '' || $data['file_path'] === null)) {
                $data['file_path'] = '';
            }

            // Handle text value
            $data['text_value'] = $data['text_value'] ?? '';

            // Handle JSON value
            $data['json_value'] = $data['json_value'] ?? '';

            // Handle products value
            $productsData = null;
            if (isset($data['selected_products']) && !empty(trim($data['selected_products']))) {
                $productsData = $data['selected_products'];
            }
            $products = [];
            if (!empty($productsData)) {
                if (is_array($productsData)) {
                    $products = $productsData;
                } else {
                    $decoded = json_decode($productsData, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $products = $decoded;
                    }
                }
            }
            $data['products_value'] = !empty($products) ? json_encode($products, JSON_UNESCAPED_UNICODE) : '';

            // Handle categories value
            $categoryData = null;
            if (isset($data['selected_categories']) && !empty(trim($data['selected_categories']))) {
                $categoryData = $data['selected_categories'];
            }
            $categories = [];
            if (!empty($categoryData)) {
                if (is_array($categoryData)) {
                    $categories = $categoryData;
                } else {
                    $decoded = json_decode($categoryData, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $categories = $decoded;
                    }
                }
            }
            $data['categories_value'] = !empty($categories) ? json_encode($categories, JSON_UNESCAPED_UNICODE) : '';

            // Handle CMS pages value
            $cmsPagesData = null;
            if (isset($data['selected_cms_pages']) && !empty(trim($data['selected_cms_pages']))) {
                $cmsPagesData = $data['selected_cms_pages'];
            }
            $cmsPages = [];
            if (!empty($cmsPagesData)) {
                if (is_array($cmsPagesData)) {
                    $cmsPages = $cmsPagesData;
                } else {
                    $decoded = json_decode($cmsPagesData, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $cmsPages = $decoded;
                    }
                }
            }
            $data['cms_pages_value'] = !empty($cmsPages) ? json_encode($cmsPages, JSON_UNESCAPED_UNICODE) : '';

            // Handle CMS include content (Yes/No select - values are "1" or "0" string)
            $val = $data['cms_include_content'] ?? 0;
            $data['cms_include_content'] = ($val === 1 || $val === '1' || $val === true) ? 1 : 0;

            // Set value_type based on which primary value column has data
            if (!empty($data['cms_pages_value'])) {
                $data['value_type'] = 'cms';
            } elseif (!empty($data['products_value'])) {
                $data['value_type'] = 'products';
            } elseif (!empty($data['categories_value'])) {
                $data['value_type'] = 'category';
            } elseif (!empty($data['json_value'])) {
                $data['value_type'] = 'json';
            } elseif (!empty($data['file_path'])) {
                $data['value_type'] = 'file';
            } elseif (!empty($data['text_value'])) {
                $data['value_type'] = 'text';
            }

            // Final safety check: if file_path is explicitly empty string, ensure it's cleared
            // This MUST be the last check before setting data to model
            // This handles the case where file_path is sent as empty string in payload
            if (isset($data['file_path']) && (trim($data['file_path']) === '' || $data['file_path'] === null)) {
                $data['file_path'] = '';
            }

            // Also check if file array is empty, ensure file_path is also empty
            if (isset($data['file']) && is_array($data['file']) && empty($data['file'])) {
                $data['file_path'] = '';
            }

            $model->setData($data);

            try {
                // Validate required fields
                if (empty($data['key_name'])) {
                    throw new LocalizedException(__('Key Name is required.'));
                }

                $model->save();

                // Verify save was successful
                if (!$model->getId()) {
                    throw new LocalizedException(__('Failed to save the key-value pair. Model ID is missing after save.'));
                }

                $this->messageManager->addSuccessMessage(__('You saved the key-value pair.'));

                if ($this->getRequest()->getParam('back')) {
                    return $resultRedirect->setPath('*/*/edit', ['keyvalue_id' => $model->getId()]);
                }

                return $resultRedirect->setPath('*/*/');
            } catch (LocalizedException $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the key-value pair: %1', $e->getMessage()));
            }

            $this->_getSession()->setFormData($data);
            if ($id) {
                return $resultRedirect->setPath('*/*/edit', ['keyvalue_id' => $id]);
            }
            return $resultRedirect->setPath('*/*/new');
        }

        return $resultRedirect->setPath('*/*/');
    }
}



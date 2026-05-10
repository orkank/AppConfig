<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Plugin\Cms\Controller\Adminhtml\Wysiwyg\Images;

use Magento\Cms\Controller\Adminhtml\Wysiwyg\Images\Upload;
use Magento\Framework\App\RequestInterface;

/**
 * Uppy/XHR uploads send "type" as the file MIME (e.g. video/mp4). That overwrites the intended
 * storage kind from the media browser URL (type/file, type/image). Storage::uploadFile() then
 * uses MIME as $type → wrong extension list → "File validation failed".
 */
class UploadPlugin
{
    public function beforeExecute(Upload $subject): void
    {
        $request = $subject->getRequest();
        $type = $request->getParam('type');
        if ($type === null || $type === '') {
            return;
        }
        $typeString = (string) $type;
        if (strpos($typeString, '/') === false) {
            return;
        }
        // MIME string → map to storage kind: image/* vs everything else (video, pdf, …) → file
        if (stripos($typeString, 'image/') === 0) {
            $request->setParam('type', 'image');
        } else {
            $request->setParam('type', 'file');
        }
    }
}

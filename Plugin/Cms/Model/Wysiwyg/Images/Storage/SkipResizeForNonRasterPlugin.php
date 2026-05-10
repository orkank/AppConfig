<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Plugin\Cms\Model\Wysiwyg\Images\Storage;

use Magento\Cms\Model\Wysiwyg\Images\Storage;

/**
 * Core uploadFile() always calls resizeFile(); image adapter cannot open video/PDF — skip thumbnail step.
 */
class SkipResizeForNonRasterPlugin
{
    private const SKIP_THUMB_EXTENSIONS = [
        'mp4', 'webm', 'mov', 'avi', 'mkv', 'flv', 'wmv', 'ogv', 'm4v', '3gp',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar', '7z',
    ];

    public function aroundResizeFile(Storage $subject, callable $proceed, $source, $keepRatio = true)
    {
        $ext = strtolower((string) pathinfo((string) $source, PATHINFO_EXTENSION));
        if ($ext !== '' && in_array($ext, self::SKIP_THUMB_EXTENSIONS, true)) {
            return false;
        }

        return $proceed($source, $keepRatio);
    }
}

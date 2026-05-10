<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Plugin\Cms\Model\Wysiwyg\Images\Storage;

use Magento\Cms\Model\Wysiwyg\Images\Storage;

/**
 * Çekirdek di birleştirmesi "allowed" / "image_allowed" için son modülde üst üste yazar;
 * getFilesCollection ise type=null veya image iken bu listeleri kullanır — mp4 diskte kalır ama grid boş kalır.
 * Tüm type'lar için (listing + doğrulama) video uzantı anahtarlarını ekler.
 */
class MergeVideoIntoAllowedExtensionsPlugin
{
    /**
     * Storage $_extensions item keys (regex parçası); mp4_application vb. MIME varyantları upload ile uyumlu.
     *
     * @var string[]
     */
    private const EXTRA_EXTENSION_KEYS = [
        'mp4',
        'mp4_application',
        'mp4_audio',
        'webm',
        'ogv',
        'mov',
        'm4v',
    ];

    /**
     * @param string[] $result
     * @return string[]
     */
    public function afterGetAllowedExtensions(Storage $subject, array $result, $type = null): array
    {
        return array_values(array_unique(array_merge($result, self::EXTRA_EXTENSION_KEYS)));
    }
}

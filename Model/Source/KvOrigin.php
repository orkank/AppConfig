<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Model\Source;

use IDangerous\AppConfig\Model\Headless\Origin;
use Magento\Framework\Data\OptionSourceInterface;

class KvOrigin implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => Origin::ADMIN, 'label' => __('Admin')],
            ['value' => Origin::HEADLESS, 'label' => __('Headless / API')],
        ];
    }
}

<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ValueType implements OptionSourceInterface
{
    /**
     * Return array of options as value-label pairs
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'text', 'label' => __('Text')],
            ['value' => 'file', 'label' => __('File')],
            ['value' => 'json', 'label' => __('JSON')],
            ['value' => 'products', 'label' => __('Products')],
            ['value' => 'category', 'label' => __('Category')],
            ['value' => 'cms', 'label' => __('CMS Pages')]
        ];
    }
}



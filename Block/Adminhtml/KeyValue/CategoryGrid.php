<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Block\Adminhtml\KeyValue;

use Magento\Backend\Block\Widget\Container;
use Magento\Backend\Block\Widget\Context;

class CategoryGrid extends Container
{
    /**
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Prepare layout
     *
     * @return $this
     */
    protected function _prepareLayout()
    {
        $this->setChild(
            'grid',
            $this->getLayout()->createBlock(
                \Magento\Catalog\Block\Adminhtml\Category\Checkboxes\Tree::class,
                'appconfig.category.tree',
                [
                    'data' => [
                        'js_form_object' => 'category_selector'
                    ]
                ]
            )
        );
        return parent::_prepareLayout();
    }

    /**
     * Render grid
     *
     * @return string
     */
    public function getGridHtml()
    {
        return $this->getChildHtml('grid');
    }

    /**
     * Render block HTML
     *
     * @return string
     */
    protected function _toHtml()
    {
        return $this->getGridHtml();
    }
}

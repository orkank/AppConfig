<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Block\Adminhtml\KeyValue\CmsPageGrid;

use Magento\Backend\Block\Widget\Grid\Extended;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Helper\Data;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory;

class Grid extends Extended
{
    /**
     * @var CollectionFactory
     */
    protected $pageCollectionFactory;

    /**
     * @param Context $context
     * @param Data $backendHelper
     * @param CollectionFactory $pageCollectionFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        Data $backendHelper,
        CollectionFactory $pageCollectionFactory,
        array $data = []
    ) {
        $this->pageCollectionFactory = $pageCollectionFactory;
        parent::__construct($context, $backendHelper, $data);
    }

    /**
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setId('appconfig_cmspage_grid');
        $this->setDefaultSort('page_id');
        $this->setDefaultDir('DESC');
        $this->setUseAjax(true);
        $this->setSaveParametersInSession(true);
    }

    /**
     * Prepare collection
     *
     * @return $this
     */
    protected function _prepareCollection()
    {
        $collection = $this->pageCollectionFactory->create();
        $collection->setFirstStoreFlag(true);
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    /**
     * Prepare columns
     *
     * @return $this
     */
    protected function _prepareColumns()
    {
        $this->addColumn(
            'in_pages',
            [
                'type' => 'checkbox',
                'name' => 'appconfig_selected_cms_pages',
                'align' => 'center',
                'index' => 'page_id',
                'field_name' => 'appconfig_selected_cms_pages',
                'use_index' => true,
                'header_css_class' => 'col-select',
                'column_css_class' => 'col-select',
                'values' => []
            ]
        );

        $this->addColumn(
            'page_id',
            [
                'header' => __('ID'),
                'type' => 'number',
                'index' => 'page_id',
                'header_css_class' => 'col-id',
                'column_css_class' => 'col-id'
            ]
        );

        $this->addColumn(
            'title',
            [
                'header' => __('Title'),
                'index' => 'title',
                'header_css_class' => 'col-title',
                'column_css_class' => 'col-title'
            ]
        );

        $this->addColumn(
            'identifier',
            [
                'header' => __('URL Key'),
                'index' => 'identifier',
                'header_css_class' => 'col-identifier',
                'column_css_class' => 'col-identifier'
            ]
        );

        $this->addColumn(
            'update_time',
            [
                'header' => __('Modified'),
                'index' => 'update_time',
                'type' => 'datetime',
                'header_css_class' => 'col-date',
                'column_css_class' => 'col-date'
            ]
        );

        return parent::_prepareColumns();
    }

    /**
     * Get grid URL
     *
     * @return string
     */
    public function getGridUrl()
    {
        return $this->getUrl('appconfig/keyvalue/cmsgrid', ['_current' => true]);
    }
}

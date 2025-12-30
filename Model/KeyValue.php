<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Model;

use IDangerous\AppConfig\Model\ResourceModel\KeyValue as KeyValueResource;
use Magento\Framework\Model\AbstractModel;

class KeyValue extends AbstractModel
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(KeyValueResource::class);
    }

    /**
     * Get text value
     *
     * @return string|null
     */
    public function getTextValue()
    {
        return $this->getData('text_value');
    }

    /**
     * Get JSON value
     *
     * @return string|null
     */
    public function getJsonValue()
    {
        return $this->getData('json_value');
    }

    /**
     * Get products value
     *
     * @return string|null
     */
    public function getProductsValue()
    {
        return $this->getData('products_value');
    }

    /**
     * Get categories value
     *
     * @return string|null
     */
    public function getCategoriesValue()
    {
        return $this->getData('categories_value');
    }
}



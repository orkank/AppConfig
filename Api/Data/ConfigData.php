<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Api\Data;

use Magento\Framework\Api\AbstractSimpleObject;

class ConfigData extends AbstractSimpleObject implements ConfigDataInterface
{
    /**
     * @inheritDoc
     */
    public function getKey()
    {
        return $this->_get('key');
    }

    /**
     * @inheritDoc
     */
    public function setKey($key)
    {
        return $this->setData('key', $key);
    }

    /**
     * @inheritDoc
     */
    public function getValue()
    {
        return $this->_get('value');
    }

    /**
     * @inheritDoc
     */
    public function setValue($value)
    {
        return $this->setData('value', $value);
    }

    /**
     * @inheritDoc
     */
    public function getValueType()
    {
        return $this->_get('value_type');
    }

    /**
     * @inheritDoc
     */
    public function setValueType($valueType)
    {
        return $this->setData('value_type', $valueType);
    }

    /**
     * @inheritDoc
     */
    public function getFilePath()
    {
        return $this->_get('file_path');
    }

    /**
     * @inheritDoc
     */
    public function setFilePath($filePath)
    {
        return $this->setData('file_path', $filePath);
    }

    /**
     * @inheritDoc
     */
    public function getGroupCode()
    {
        return $this->_get('group_code');
    }

    /**
     * @inheritDoc
     */
    public function setGroupCode($groupCode)
    {
        return $this->setData('group_code', $groupCode);
    }
}



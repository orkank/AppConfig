<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Api\Data;

use Magento\Framework\Api\AbstractSimpleObject;

class GroupData extends AbstractSimpleObject implements GroupDataInterface
{
    /**
     * @inheritDoc
     */
    public function getGroupId()
    {
        return $this->_get('group_id');
    }

    /**
     * @inheritDoc
     */
    public function setGroupId($groupId)
    {
        return $this->setData('group_id', $groupId);
    }

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return $this->_get('name');
    }

    /**
     * @inheritDoc
     */
    public function setName($name)
    {
        return $this->setData('name', $name);
    }

    /**
     * @inheritDoc
     */
    public function getCode()
    {
        return $this->_get('code');
    }

    /**
     * @inheritDoc
     */
    public function setCode($code)
    {
        return $this->setData('code', $code);
    }

    /**
     * @inheritDoc
     */
    public function getDescription()
    {
        return $this->_get('description');
    }

    /**
     * @inheritDoc
     */
    public function setDescription($description)
    {
        return $this->setData('description', $description);
    }

    /**
     * @inheritDoc
     */
    public function getVersion()
    {
        return $this->_get('version');
    }

    /**
     * @inheritDoc
     */
    public function setVersion($version)
    {
        return $this->setData('version', $version);
    }
}



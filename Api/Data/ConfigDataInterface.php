<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Api\Data;

/**
 * Config Data Interface
 */
interface ConfigDataInterface
{
    /**
     * Get key name
     *
     * @return string
     */
    public function getKey();

    /**
     * Set key name
     *
     * @param string $key
     * @return $this
     */
    public function setKey($key);

    /**
     * Get value
     *
     * @return string|null
     */
    public function getValue();

    /**
     * Set value
     *
     * @param string|null $value
     * @return $this
     */
    public function setValue($value);

    /**
     * Get value type
     *
     * @return string
     */
    public function getValueType();

    /**
     * Set value type
     *
     * @param string $valueType
     * @return $this
     */
    public function setValueType($valueType);

    /**
     * Get file path
     *
     * @return string|null
     */
    public function getFilePath();

    /**
     * Set file path
     *
     * @param string|null $filePath
     * @return $this
     */
    public function setFilePath($filePath);

    /**
     * Get group code
     *
     * @return string|null
     */
    public function getGroupCode();

    /**
     * Set group code
     *
     * @param string|null $groupCode
     * @return $this
     */
    public function setGroupCode($groupCode);
}



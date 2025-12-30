<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Api\Data;

/**
 * Group Data Interface
 */
interface GroupDataInterface
{
    /**
     * Get group ID
     *
     * @return int
     */
    public function getGroupId();

    /**
     * Set group ID
     *
     * @param int $groupId
     * @return $this
     */
    public function setGroupId($groupId);

    /**
     * Get name
     *
     * @return string
     */
    public function getName();

    /**
     * Set name
     *
     * @param string $name
     * @return $this
     */
    public function setName($name);

    /**
     * Get code
     *
     * @return string
     */
    public function getCode();

    /**
     * Set code
     *
     * @param string $code
     * @return $this
     */
    public function setCode($code);

    /**
     * Get description
     *
     * @return string|null
     */
    public function getDescription();

    /**
     * Set description
     *
     * @param string|null $description
     * @return $this
     */
    public function setDescription($description);

    /**
     * Get version
     *
     * @return string|null
     */
    public function getVersion();

    /**
     * Set version
     *
     * @param string|null $version
     * @return $this
     */
    public function setVersion($version);
}



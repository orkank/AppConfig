<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Api;

/**
 * App Config API Interface
 */
interface AppConfigInterface
{
    /**
     * Get configuration data by app version
     *
     * @param string|null $appVersion App version (e.g., "4.0.10+80")
     * @param string|null $groupCode Group code filter (optional)
     * @return array Format: ['DEFAULTS' => [...], 'GROUPS' => ['group_code' => [...]]]
     */
    public function getConfig($appVersion = null, $groupCode = null);

    /**
     * Get configuration groups
     *
     * @param string|null $appVersion App version (e.g., "4.0.10+80")
     * @return array Format: ['group_code' => ['name' => ..., 'description' => ..., 'version' => ...]]
     */
    public function getGroups($appVersion = null);

    /**
     * Get single configuration value
     *
     * @param string $key Configuration Key
     * @param string|null $groupCode Optional Group Code to filter
     * @param string|null $appVersion Optional App Version
     * @return mixed|null Value or null if not found
     */
    public function getValue($key, $groupCode = null, $appVersion = null);
}



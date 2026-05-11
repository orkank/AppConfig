<?php
declare(strict_types=1);

namespace IDangerous\AppConfig\Model;

/**
 * Admin UI listing filter for Key-Value grid (stored in backend session).
 */
final class ListOriginScope
{
    public const SESSION_KEY = 'appconfig_kv_origin_scope';

    public const ADMIN_ONLY = 'admin';

    public const HEADLESS_ONLY = 'headless';

    public const ALL = 'all';
}

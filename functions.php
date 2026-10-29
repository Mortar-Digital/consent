<?php

declare(strict_types=1);

use MortarDigital\Consent\WordPress\Plugin;

if (! defined('ABSPATH')) {
    exit;
}

if (! function_exists('mortar_consent_has')) {
    /**
     * Whether the current visitor allows a consent category, for server-side
     * checks. Varying output on this defeats full-page caching, so prefer
     * gating scripts client-side (`data-mortar-consent`) where possible.
     */
    function mortar_consent_has(string $category): bool
    {
        return Plugin::instance()?->has($category) ?? false;
    }
}

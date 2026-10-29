<?php

declare(strict_types=1);

use MortarDigital\Consent\WordPress\Plugin;

if (! function_exists('md_consent_has')) {
    /**
     * Whether the current visitor allows a consent category, for server-side
     * checks. Varying output on this defeats full-page caching, so prefer
     * gating scripts client-side (`data-md-consent`) where possible.
     */
    function md_consent_has(string $category): bool
    {
        return Plugin::instance()?->has($category) ?? false;
    }
}

<?php

declare(strict_types=1);

use MortarDigital\Consent\WordPress\Plugin;

// Integration tests load the real WordPress in wp-env, with the plugin active,
// so they exercise WordPress's own filters, REST server and database. Run them
// with `composer test:integration`, which runs PHPUnit inside the container.
$wpLoad = getenv('WP_LOAD_PATH') ?: '/var/www/html/wp-load.php';

if (! is_file($wpLoad)) {
    fwrite(STDERR, "WordPress not found at {$wpLoad}. Start wp-env and run `composer test:integration`.\n");
    exit(1);
}

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require $wpLoad;

if (Plugin::instance() === null) {
    fwrite(STDERR, "Mortar Consent is not active. Run `wp plugin activate mortar-consent` in wp-env.\n");
    exit(1);
}

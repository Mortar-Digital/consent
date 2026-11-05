<?php

/**
 * Plugin Name:       Mortar Consent
 * Plugin URI:        https://mortar.digital/plugins/consent/
 * Description:       Cookie consent that is opt-in, lets visitors choose and change their mind, and never calls third-party services.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.2
 * Author:            Mortar Digital
 * Author URI:        https://mortar.digital/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mortar-consent
 */

declare(strict_types=1);

use MortarDigital\Consent\WordPress\Plugin;

if (! defined('ABSPATH')) {
    exit;
}

// The same package can also run as an MU-plugin.
// Never boot twice.
if (class_exists(Plugin::class) && Plugin::instance() !== null) {
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-warning"><p>' . esc_html__('Mortar Consent is already loaded as a must-use plugin. Deactivate one of them.', 'mortar-consent') . '</p></div>';
    });

    return;
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'MortarDigital\\Consent\\';

    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

        if (is_file($file)) {
            require $file;
        }
    }
});

require_once __DIR__ . '/functions.php';

Plugin::boot(__DIR__, plugins_url('', __FILE__));

register_deactivation_hook(__FILE__, static function (): void {
    wp_clear_scheduled_hook(Plugin::PURGE_HOOK);
});

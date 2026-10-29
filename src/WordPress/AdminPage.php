<?php

declare(strict_types=1);

namespace MortarDigital\Consent\WordPress;

/**
 * Settings → Cookie consent. The screen itself is a small React app
 * (assets/admin.js) that talks to the mortar-consent/v1 REST routes.
 */
final class AdminPage
{
    private const SLUG = 'mortar-consent';
    private const EXPORT_ACTION = 'mortar_consent_export';

    private string $hook = '';

    public function __construct(
        private readonly string $url,
        private readonly string $dir,
        private readonly ConsentLog $log,
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', function (): void {
            $this->hook = (string) add_options_page(
                __('Cookie consent', 'mortar-consent'),
                __('Cookie consent', 'mortar-consent'),
                RestController::capability(),
                self::SLUG,
                $this->render(...),
            );
        });
        add_action('admin_enqueue_scripts', $this->enqueue(...));
        add_action('admin_post_' . self::EXPORT_ACTION, $this->export(...));
        add_action('admin_init', $this->suggestPrivacyPolicyText(...));
        // A "Settings" link on the plugin's row, whether it is listed under
        // Must-Use (the MU adapter) or as the standalone plugin; both files
        // are named mortar-consent.php.
        add_filter('plugin_action_links', static function (array $actions, string $file): array {
            if (basename($file) !== 'mortar-consent.php' || ! current_user_can(RestController::capability())) {
                return $actions;
            }

            return [
                'settings' => sprintf('<a href="%s">%s</a>', esc_url(admin_url('options-general.php?page=' . self::SLUG)), esc_html__('Settings', 'mortar-consent')),
                ...$actions,
            ];
        }, 10, 2);
    }

    /** Suggested wording for Settings → Privacy → Policy Guide. */
    public function suggestPrivacyPolicyText(): void
    {
        $paragraphs = [
            __('We ask for your consent before setting optional cookies or loading third-party content. Your choice is stored in a cookie named mortar_consent on your device. It holds a random consent ID, the version of our cookie policy you saw, the time you chose, and the categories you allowed. It does not identify you.', 'mortar-consent'),
            __('When you make or change a choice, we keep a record of it on our server as proof of consent: the consent ID, the policy version, the categories you allowed, how you chose (accept all, reject all or custom) and the time. The record does not include your IP address, browser details or user account. We keep these records for the period set in our cookie settings (three years by default) and then delete them automatically.', 'mortar-consent'),
            __('To stop misuse of the consent record endpoint, we briefly count requests using a keyed hash of your IP address. The address itself is not stored, and the counters expire after about ten minutes.', 'mortar-consent'),
            __('Embedded content such as videos and maps is not loaded until you allow it. Once loaded, it comes from the provider (for example YouTube or Google Maps), which may set its own cookies and collect data under its own privacy policy.', 'mortar-consent'),
            __('You can change or withdraw your consent at any time using the cookie settings link. Your consent ID is shown there, so you can quote it if you ask us about your record.', 'mortar-consent'),
        ];
        $content = '<p class="privacy-policy-tutorial">' . esc_html__('Mortar Consent stores the visitor choices described below. Match the retention period to your settings, drop the consent record paragraphs if logging is off, and list the cookies your other plugins and embeds set.', 'mortar-consent') . '</p>'
            . '<strong class="privacy-policy-tutorial">' . esc_html__('Suggested text:', 'mortar-consent') . '</strong> '
            . implode('', array_map(static fn (string $p): string => '<p>' . esc_html($p) . '</p>', $paragraphs));

        wp_add_privacy_policy_content(__('Mortar Consent', 'mortar-consent'), $content);
    }

    public function render(): void
    {
        ?>
<div class="wrap">
<h1><?php esc_html_e('Cookie consent', 'mortar-consent'); ?></h1>
<p class="mortar-consent-intro"><?php esc_html_e('Choose what visitors are asked, which scripts and embeds wait for their consent, and review the choices they make.', 'mortar-consent'); ?></p>
<div id="mortar-consent-admin"><p class="mortar-consent-loading"><?php esc_html_e('Loading…', 'mortar-consent'); ?></p></div>
<noscript><p><?php esc_html_e('This screen needs JavaScript.', 'mortar-consent'); ?></p></noscript>
</div>
        <?php
    }

    public function enqueue(string $hook): void
    {
        if ($hook !== $this->hook || $this->hook === '') {
            return;
        }
        $version = static function (string $path): string {
            $mtime = @filemtime($path);

            return $mtime === false ? '0' : (string) $mtime;
        };
        wp_enqueue_script('mortar-consent-admin', $this->url . '/assets/admin.js', ['wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n'], $version($this->dir . '/assets/admin.js'), ['in_footer' => true]);
        wp_set_script_translations('mortar-consent-admin', 'mortar-consent');
        wp_add_inline_script('mortar-consent-admin', 'window.mortarConsentAdmin = ' . wp_json_encode([
            'exportUrl' => wp_nonce_url(admin_url('admin-post.php?action=' . self::EXPORT_ACTION), self::EXPORT_ACTION),
        ], JSON_HEX_TAG | JSON_HEX_AMP) . ';', 'before');
        wp_enqueue_style('mortar-consent-admin', $this->url . '/assets/admin.css', ['wp-components'], $version($this->dir . '/assets/admin.css'));
    }

    /** Streams the consent log as CSV. Capability and nonce checked first. */
    public function export(): void
    {
        if (! current_user_can(RestController::capability())) {
            wp_die(esc_html__('You are not allowed to export consent records.', 'mortar-consent'), '', ['response' => 403]);
        }
        check_admin_referer(self::EXPORT_ACTION);

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="consent-log-' . gmdate('Ymd-His') . '.csv"');
        header('X-Content-Type-Options: nosniff');
        $this->log->exportCsv(static function (string $chunk): void {
            echo $chunk; // phpcs:ignore WordPress.Security.EscapeOutput -- CSV, escaped by Csv::row().
        });
        exit;
    }
}

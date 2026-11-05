<?php

declare(strict_types=1);

namespace MortarDigital\Consent\WordPress;

use MortarDigital\Consent\Category;
use MortarDigital\Consent\Config;

/**
 * Prints the banner, preferences dialog and re-open control. The markup is
 * identical for every visitor (hidden until the runtime decides), so pages
 * stay cacheable and nothing appears without JavaScript.
 */
final class Renderer
{
    private bool $rendered = false;

    /** @param array<string, mixed> $clientExtras runtime settings that are not part of Config (e.g. the log endpoint) */
    public function __construct(
        private readonly Config $config,
        private readonly array $clientExtras = [],
    ) {
    }

    public function register(): void
    {
        // Early in <body> so the banner comes first in focus and reading order;
        // wp_footer covers themes that do not call wp_body_open().
        add_action('wp_body_open', $this->render(...), 5);
        add_action('wp_footer', $this->render(...), 5);
    }

    public function render(): void
    {
        if ($this->rendered || is_admin() || is_feed() || is_embed()) {
            return;
        }

        $this->rendered = true;

        $t = $this->config->texts;
        $json = wp_json_encode([...$this->config->toClientArray(), ...$this->clientExtras], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
        ?>
<div class="mortar-consent" id="mortar-consent">
<script type="application/json" id="mortar-consent-config"><?php echo $json; /* phpcs:ignore WordPress.Security.EscapeOutput -- JSON with HTML-significant characters hex-escaped. */ ?></script>
<section class="mortar-consent__banner" id="mortar-consent-banner" aria-labelledby="mortar-consent-banner-title" aria-describedby="mortar-consent-banner-body" hidden>
<h2 class="mortar-consent__title" id="mortar-consent-banner-title"><?php echo esc_html($t['banner_title']); ?></h2>
<p class="mortar-consent__body" id="mortar-consent-banner-body"><?php echo esc_html($t['banner_body']); ?><?php $this->privacyLink(); ?></p>
<?php $this->gpcNotice(); ?>
<div class="mortar-consent__actions">
<button type="button" class="mortar-consent__button" data-mortar-consent-action="accept-all"><?php echo esc_html($t['accept_all']); ?></button>
<button type="button" class="mortar-consent__button" data-mortar-consent-action="reject-all"><?php echo esc_html($t['reject_all']); ?></button>
<button type="button" class="mortar-consent__button" data-mortar-consent-action="manage"><?php echo esc_html($t['manage']); ?></button>
</div>
</section>
<dialog class="mortar-consent__dialog" id="mortar-consent-dialog" aria-labelledby="mortar-consent-dialog-title">
<form class="mortar-consent__form" method="dialog" data-mortar-consent-form>
<div class="mortar-consent__dialog-header">
<h2 class="mortar-consent__title" id="mortar-consent-dialog-title"><?php echo esc_html($t['dialog_title']); ?></h2>
<button type="button" class="mortar-consent__close" data-mortar-consent-action="close" aria-label="<?php echo esc_attr($t['close']); ?>"><span aria-hidden="true">&times;</span></button>
</div>
<p class="mortar-consent__body"><?php echo esc_html($t['dialog_body']); ?><?php $this->privacyLink(); ?></p>
<?php $this->gpcNotice(); ?>
<ul class="mortar-consent__categories" role="list">
<?php foreach ($this->config->policy->categories->all() as $category) {
    $this->category($category);
} ?>
</ul>
<p class="mortar-consent__meta" data-mortar-consent-meta hidden><?php echo esc_html($t['consent_id']); ?>: <code data-mortar-consent-id></code></p>
<div class="mortar-consent__actions">
<button type="button" class="mortar-consent__button" data-mortar-consent-action="accept-all"><?php echo esc_html($t['accept_all']); ?></button>
<button type="button" class="mortar-consent__button" data-mortar-consent-action="reject-all"><?php echo esc_html($t['reject_all']); ?></button>
<button type="submit" class="mortar-consent__button" data-mortar-consent-action="save"><?php echo esc_html($t['save']); ?></button>
</div>
</form>
</dialog>
<?php if ($this->config->reopenButton) : ?>
<button type="button" class="mortar-consent__reopen" data-mortar-consent-open hidden><?php echo esc_html($t['settings_button']); ?></button>
<?php endif; ?>
</div>
        <?php
    }

    /** Markup for the settings button block and shortcode. */
    public function settingsButton(string $label, string $wrapperAttributes = 'class="mortar-consent-open wp-element-button"'): string
    {
        $label = trim($label) !== '' ? $label : $this->config->texts['settings_button'];

        return sprintf(
            '<button type="button" %s data-mortar-consent-open>%s</button>',
            $wrapperAttributes, // Built by get_block_wrapper_attributes(), which escapes.
            esc_html($label),
        );
    }

    /**
     * The cookie declaration: every category with the cookies it sets. Same
     * for every visitor, so it caches with the page.
     */
    public function declaration(bool $showSettingsButton, string $wrapperAttributes): string
    {
        $headingId = function_exists('wp_unique_id') ? wp_unique_id('mortar-consent-declaration-') : 'mortar-consent-declaration';
        ob_start();
        ?>
<div <?php echo $wrapperAttributes; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by get_block_wrapper_attributes(), which escapes. */ ?>>
<?php foreach ($this->config->policy->categories->all() as $category) : ?>
<section class="mortar-consent-declaration__category" aria-labelledby="<?php echo esc_attr($headingId . '-' . $category->id); ?>">
<h3 id="<?php echo esc_attr($headingId . '-' . $category->id); ?>"><?php echo esc_html($category->label); ?><?php if ($category->required) : ?> <span class="mortar-consent-declaration__always">(<?php echo esc_html($this->config->texts['always_active']); ?>)</span><?php endif; ?></h3>
<?php if ($category->description !== '') : ?>
<p><?php echo esc_html($category->description); ?></p>
<?php endif; ?>
<?php if ($category->cookies !== []) : ?>
<div class="mortar-consent-declaration__table-wrap">
<table class="mortar-consent-declaration__table">
<thead><tr>
<th scope="col"><?php esc_html_e('Cookie', 'mortar-consent'); ?></th>
<th scope="col"><?php esc_html_e('Provider', 'mortar-consent'); ?></th>
<th scope="col"><?php esc_html_e('Purpose', 'mortar-consent'); ?></th>
<th scope="col"><?php esc_html_e('Duration', 'mortar-consent'); ?></th>
</tr></thead>
<tbody>
<?php foreach ($category->cookies as $cookie) : ?>
<tr>
<th scope="row"><code><?php echo esc_html($cookie->name); ?></code></th>
<td><?php echo esc_html($cookie->provider); ?></td>
<td><?php echo esc_html($cookie->purpose); ?></td>
<td><?php echo esc_html($cookie->duration); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
</section>
<?php endforeach; ?>
<?php if ($showSettingsButton) : ?>
<p><?php echo $this->settingsButton(''); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. */ ?></p>
<?php endif; ?>
</div>
        <?php

        return (string) ob_get_clean();
    }

    private function category(Category $category): void
    {
        $id = 'mortar-consent-category-' . $category->id;
        ?>
<li class="mortar-consent__category">
<div class="mortar-consent__category-header">
<label class="mortar-consent__label" for="<?php echo esc_attr($id); ?>"><?php echo esc_html($category->label); ?></label>
<?php if ($category->required) : ?>
<span class="mortar-consent__always"><?php echo esc_html($this->config->texts['always_active']); ?></span>
<input class="mortar-consent__switch" type="checkbox" role="switch" id="<?php echo esc_attr($id); ?>" checked disabled<?php echo $category->description !== '' ? ' aria-describedby="' . esc_attr($id . '-description') . '"' : ''; ?>>
<?php else : ?>
<input class="mortar-consent__switch" type="checkbox" role="switch" id="<?php echo esc_attr($id); ?>" name="mortar-consent-category" value="<?php echo esc_attr($category->id); ?>"<?php echo $category->description !== '' ? ' aria-describedby="' . esc_attr($id . '-description') . '"' : ''; ?>>
<?php endif; ?>
</div>
<?php if ($category->description !== '') : ?>
<p class="mortar-consent__description" id="<?php echo esc_attr($id . '-description'); ?>"><?php echo esc_html($category->description); ?></p>
<?php endif; ?>
</li>
        <?php
    }

    /** Shown by the runtime only when the browser sends Global Privacy Control. */
    private function gpcNotice(): void
    {
        if ($this->config->gpcCategories === []) {
            return;
        }

        $labels = array_map(
            fn (string $id): string => $this->config->policy->categories->get($id)->label,
            $this->config->gpcCategories,
        );

        printf(
            '<p class="mortar-consent__gpc" data-mortar-consent-gpc hidden>%s</p>',
            esc_html(str_replace('%s', implode(', ', $labels), $this->config->texts['gpc_notice'])),
        );
    }

    private function privacyLink(): void
    {
        if ($this->config->privacyPolicyUrl === '') {
            return;
        }

        printf(
            ' <a class="mortar-consent__link" href="%s">%s</a>',
            esc_url($this->config->privacyPolicyUrl, ['http', 'https']),
            esc_html($this->config->texts['privacy_link']),
        );
    }
}

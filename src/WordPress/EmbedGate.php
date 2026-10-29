<?php

declare(strict_types=1);

namespace MortarDigital\Consent\WordPress;

use MortarDigital\Consent\Config;

/**
 * Click-to-load placeholders for third-party embeds. The original markup is
 * kept inside an inert <template>, so iframes and scripts neither load nor
 * run until the visitor allows the category or loads that one embed.
 *
 * Covers core/embed blocks, iframes in any other leaf block, and anything
 * left in post content afterwards (classic posts, auto-embedded URLs).
 * Third-party iframes from unknown hosts are gated too (provider "other"),
 * so a new embed service fails closed. Same-site iframes are left alone.
 */
final class EmbedGate
{
    /** Hosts recognised in raw iframes => provider slug (matches core/embed slugs). */
    private const HOSTS = [
        'youtube.com' => 'youtube',
        'youtube-nocookie.com' => 'youtube',
        'youtu.be' => 'youtube',
        'vimeo.com' => 'vimeo',
        'dailymotion.com' => 'dailymotion',
        'spotify.com' => 'spotify',
        'soundcloud.com' => 'soundcloud',
        'twitter.com' => 'twitter',
        'x.com' => 'twitter',
        'instagram.com' => 'instagram',
        'facebook.com' => 'facebook',
        'tiktok.com' => 'tiktok',
        'google.com' => 'google-maps',
        'maps.google.com' => 'google-maps',
        'openstreetmap.org' => 'openstreetmap',
        'calendly.com' => 'calendly',
        'typeform.com' => 'typeform',
    ];

    private const NAMES = [
        'youtube' => 'YouTube',
        'vimeo' => 'Vimeo',
        'dailymotion' => 'Dailymotion',
        'spotify' => 'Spotify',
        'soundcloud' => 'SoundCloud',
        'twitter' => 'X (Twitter)',
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'tiktok' => 'TikTok',
        'google-maps' => 'Google Maps',
        'openstreetmap' => 'OpenStreetMap',
        'calendly' => 'Calendly',
        'typeform' => 'Typeform',
    ];

    public function __construct(private readonly Config $config)
    {
    }

    public function register(): void
    {
        add_filter('render_block', $this->filterBlock(...), 20, 2);
        // After do_blocks (9) and auto-embeds (8): catches classic content and
        // anything blocks left. Already-gated iframes sit in <template>s and are skipped.
        add_filter('the_content', fn (string $content): string => is_feed() ? $content : $this->gateIframes($content), 20);
    }

    /** @param array<string, mixed> $block */
    public function filterBlock(string $content, array $block): string
    {
        if ($content === '' || is_feed() || is_admin() || ! empty($block['innerBlocks'])) {
            return $content;
        }
        if (($block['blockName'] ?? '') === 'core/embed') {
            $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
            $slug = is_string($attrs['providerNameSlug'] ?? null) ? $attrs['providerNameSlug'] : '';

            return $this->gateEmbedBlock($content, $slug !== '' ? $slug : $this->providerFromHtml($content)[0]);
        }

        return stripos($content, '<iframe') !== false ? $this->gateIframes($content) : $content;
    }

    /** Wraps the inside of .wp-block-embed__wrapper, keeping the figure and caption. */
    private function gateEmbedBlock(string $content, string $provider): string
    {
        $category = $this->config->embedCategoryFor($provider);
        if ($category === null || ! preg_match('/<div class="wp-block-embed__wrapper">/', $content, $m, PREG_OFFSET_CAPTURE)) {
            return $content;
        }
        $start = $m[0][1] + strlen($m[0][0]);
        $captionAt = strpos($content, '<figcaption', $start);
        $end = strrpos(substr($content, 0, $captionAt === false ? strlen($content) : $captionAt), '</div>');
        if ($end === false || $end < $start) {
            return $content;
        }
        $inner = substr($content, $start, $end - $start);
        if (trim($inner) === '' || str_contains($inner, 'data-md-consent-embed')) {
            return $content;
        }

        return substr($content, 0, $start) . $this->placeholder($inner, $provider, $category, '') . substr($content, $end);
    }

    /** Gates each iframe outside existing placeholders, one at a time. */
    private function gateIframes(string $content): string
    {
        if (stripos($content, '<iframe') === false) {
            return $content;
        }
        // Odd parts are placeholder templates (already gated): leave them.
        $parts = preg_split('#(<template\b[^>]*>.*?</template>)#is', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $content;
        }
        foreach ($parts as $i => $part) {
            if ($i % 2 === 1 || stripos($part, '<iframe') === false) {
                continue;
            }
            $parts[$i] = (string) preg_replace_callback('#<iframe\b[^>]*>.*?</iframe>#is', function (array $m): string {
                [$provider, $host] = $this->providerFromHtml($m[0]);
                $category = $provider === '' ? null : $this->config->embedCategoryFor($provider);

                return $category === null ? $m[0] : $this->placeholder($m[0], $provider, $category, $host);
            }, $part);
        }

        return implode('', $parts);
    }

    /**
     * Provider slug and host of the first iframe/script with a third-party
     * src: a known slug, "other" for unknown third-party hosts, or "" for
     * same-site and relative sources.
     *
     * @return array{string, string}
     */
    private function providerFromHtml(string $html): array
    {
        $tags = new \WP_HTML_Tag_Processor($html);
        while ($tags->next_tag()) {
            $src = $tags->get_attribute('src');
            if (! in_array($tags->get_tag(), ['IFRAME', 'SCRIPT'], true) || ! is_string($src)) {
                continue;
            }
            $host = strtolower((string) wp_parse_url($src, PHP_URL_HOST));
            if ($host === '' && str_starts_with($src, '//')) {
                $host = strtolower((string) wp_parse_url('https:' . $src, PHP_URL_HOST));
            }
            if ($host === '' || $this->isSameSite($host)) {
                continue;
            }
            foreach (self::HOSTS as $known => $provider) {
                if ($host === $known || str_ends_with($host, '.' . $known)) {
                    // google.com only counts for Maps embeds.
                    if ($provider === 'google-maps' && ! str_contains((string) wp_parse_url($src, PHP_URL_PATH), '/maps')) {
                        continue;
                    }

                    return [$provider, $host];
                }
            }

            return ['other', $host];
        }

        return ['', ''];
    }

    private function isSameSite(string $host): bool
    {
        $site = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));

        return $host === $site || str_ends_with($host, '.' . $site);
    }

    private function placeholder(string $original, string $provider, string $category, string $host): string
    {
        $texts = $this->config->texts;
        $name = self::NAMES[$provider] ?? ($host !== '' ? $host : ($provider !== '' && $provider !== 'other' ? ucwords(str_replace('-', ' ', $provider)) : __('a third party', 'mortar-consent')));
        $label = $this->config->policy->categories->get($category)->label;

        return sprintf(
            '<div class="md-consent-embed" data-md-consent-embed="%1$s" data-md-consent-provider="%2$s"><template>%3$s</template>'
            . '<div class="md-consent-embed__placeholder"><p class="md-consent-embed__notice">%4$s</p>'
            . '<p class="md-consent-embed__actions"><button type="button" class="md-consent-embed__button wp-element-button" data-md-consent-embed-load>%5$s</button> '
            . '<button type="button" class="md-consent-embed__button wp-element-button" data-md-consent-embed-allow>%6$s</button></p></div></div>',
            esc_attr($category),
            esc_attr($provider),
            $original, // Already-rendered embed markup, kept verbatim inside an inert template.
            esc_html(str_replace('%s', $name, $texts['embed_notice'])),
            esc_html($texts['embed_load']),
            esc_html(str_replace('%s', $label, $texts['embed_allow'])),
        );
    }
}

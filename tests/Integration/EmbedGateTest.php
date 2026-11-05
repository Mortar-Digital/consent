<?php

declare(strict_types=1);

namespace MortarDigital\Consent\Tests\Integration;

use MortarDigital\Consent\WordPress\EmbedGate;

final class EmbedGateTest extends IntegrationTestCase
{
    private const YOUTUBE_BLOCK = '<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube">'
        . '<div class="wp-block-embed__wrapper"><iframe title="Film" src="https://www.youtube.com/embed/abc"></iframe></div>'
        . '<figcaption class="wp-element-caption">A short film</figcaption></figure>';

    public function testEmbedBlockIsHeldInATemplateBehindAPlaceholder(): void
    {
        $html = (new EmbedGate(self::config()))->filterBlock(self::YOUTUBE_BLOCK, [
            'blockName' => 'core/embed',
            'attrs' => ['providerNameSlug' => 'youtube'],
            'innerBlocks' => [],
        ]);

        $this->assertStringContainsString('data-mortar-consent-embed="functional"', $html);
        $this->assertStringContainsString('data-mortar-consent-provider="youtube"', $html);
        $this->assertStringContainsString('<template><iframe title="Film" src="https://www.youtube.com/embed/abc"></iframe></template>', $html);
        $this->assertStringContainsString('YouTube', $html);
        // The caption stays visible, outside the inert template.
        $this->assertGreaterThan(strpos($html, '</template>'), strpos($html, '<figcaption'));
    }

    public function testIframesInPostContentAreGatedThroughTheContentFilter(): void
    {
        // The plugin's own EmbedGate, registered at boot, does this.
        $html = apply_filters('the_content', '<p>Before</p><iframe src="https://player.vimeo.com/video/1"></iframe>');

        $this->assertStringContainsString('data-mortar-consent-provider="vimeo"', $html);
        $this->assertStringContainsString('<template><iframe src="https://player.vimeo.com/video/1"></iframe></template>', $html);
    }

    public function testUnknownThirdPartyHostIsGatedAndNamed(): void
    {
        $html = (new EmbedGate(self::config()))->filterBlock('<iframe src="https://widgets.example.net/w"></iframe>', [
            'blockName' => 'core/html',
            'attrs' => [],
            'innerBlocks' => [],
        ]);

        $this->assertStringContainsString('data-mortar-consent-provider="other"', $html);
        $this->assertStringContainsString('widgets.example.net', $html);
    }

    public function testSameSiteIframeIsLeftAlone(): void
    {
        $iframe = '<iframe src="' . esc_url(home_url('/embed/')) . '"></iframe>';

        $html = (new EmbedGate(self::config()))->filterBlock($iframe, ['blockName' => 'core/html', 'attrs' => [], 'innerBlocks' => []]);

        $this->assertSame($iframe, $html);
    }

    public function testEmbedsAreNotGatedWhenTheyNeedNoCategory(): void
    {
        $gate = new EmbedGate(self::config(['embeds' => ['category' => '', 'providers' => []]]));

        $html = $gate->filterBlock(self::YOUTUBE_BLOCK, ['blockName' => 'core/embed', 'attrs' => ['providerNameSlug' => 'youtube'], 'innerBlocks' => []]);

        $this->assertSame(self::YOUTUBE_BLOCK, $html);
    }

    public function testAlreadyGatedContentIsNotWrappedTwice(): void
    {
        $gate = new EmbedGate(self::config());
        $block = ['blockName' => 'core/html', 'attrs' => [], 'innerBlocks' => []];
        $once = $gate->filterBlock('<iframe src="https://player.vimeo.com/video/1"></iframe>', $block);

        $this->assertSame($once, $gate->filterBlock($once, $block));
    }
}

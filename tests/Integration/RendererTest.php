<?php

declare(strict_types=1);

namespace MortarDigital\Consent\Tests\Integration;

use MortarDigital\Consent\WordPress\ConfigProvider;
use MortarDigital\Consent\WordPress\Renderer;

final class RendererTest extends IntegrationTestCase
{
    public function testBannerEscapesConfiguredText(): void
    {
        $config = self::config(['texts' => ['banner_title' => '<b>Hello</b> & welcome']]);

        $html = self::captureOutput(static fn() => (new Renderer($config))->render());

        $this->assertStringContainsString('&lt;b&gt;Hello&lt;/b&gt; &amp; welcome', $html);
        $this->assertStringNotContainsString('<b>Hello', $html);
    }

    public function testClientConfigCannotCloseItsScriptTag(): void
    {
        $extras = ['log' => 'https://example.test/</script><script>alert(1)</script>'];

        $html = self::captureOutput(static fn() => (new Renderer(self::config(), $extras))->render());

        preg_match('#<script type="application/json" id="mortar-consent-config">(.*?)</script>#s', $html, $json);
        $this->assertNotEmpty($json, 'The client config script is printed.');
        $this->assertStringNotContainsString('<', $json[1]);
        $this->assertSame($extras['log'], json_decode($json[1], true)['log']);
    }

    public function testRendersOncePerRequest(): void
    {
        $renderer = new Renderer(self::config());

        $html = self::captureOutput(static function () use ($renderer): void {
            $renderer->render();
            $renderer->render();
        });

        $this->assertSame(1, substr_count($html, 'id="mortar-consent"'));
    }

    public function testDeclarationEscapesCookieDetails(): void
    {
        $categories = (new ConfigProvider())->defaults()['categories'];
        $categories[0]['cookies'][] = ['name' => 'session_id', 'provider' => '<i>Us</i>', 'purpose' => 'Keeps you signed in & safe', 'duration' => 'Session'];
        $config = self::config(['categories' => $categories]);

        $html = (new Renderer($config))->declaration(false, 'class="mortar-consent-declaration"');

        $this->assertStringContainsString('&lt;i&gt;Us&lt;/i&gt;', $html);
        $this->assertStringContainsString('Keeps you signed in &amp; safe', $html);
        $this->assertStringNotContainsString('data-mortar-consent-open', $html);
    }
}

<?php

declare(strict_types=1);

namespace MortarDigital\Consent\Tests\Integration;

use MortarDigital\Consent\ScriptMap;
use MortarDigital\Consent\WordPress\ScriptGate;

/** Script tags printed by WordPress itself, with the gate's filters in place. */
final class ScriptGateTest extends IntegrationTestCase
{
    private mixed $savedScripts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->savedScripts = $GLOBALS['wp_scripts'] ?? null;
        $GLOBALS['wp_scripts'] = new \WP_Scripts();

        $registry = self::config()->policy->categories;
        (new ScriptGate(new ScriptMap(['it-tracker' => 'analytics', 'it-ads' => ['analytics', 'marketing']], $registry)))->register();

        wp_register_script('it-tracker', 'https://tracker.example/t.js', [], null);
        wp_add_inline_script('it-tracker', 'window.itBefore = 1;', 'before');
        wp_register_script('it-dependent', 'https://tracker.example/d.js', ['it-tracker'], null);
        wp_register_script('it-ads', 'https://ads.example/a.js', [], null);
        wp_register_script('it-plain', 'https://cdn.example/p.js', [], null);
    }

    protected function tearDown(): void
    {
        remove_all_filters('wp_script_attributes', 20);
        remove_all_filters('wp_inline_script_attributes', 20);
        $GLOBALS['wp_scripts'] = $this->savedScripts;
        parent::tearDown();
    }

    public function testMappedScriptAndItsInlineScriptArePrintedInert(): void
    {
        $html = self::captureOutput(static fn() => wp_scripts()->do_items(['it-tracker']));

        $this->assertInert(self::tag($html, 'it-tracker-js-before'), 'analytics');
        $this->assertInert(self::tag($html, 'it-tracker-js'), 'analytics');
    }

    public function testDependentScriptInheritsTheGate(): void
    {
        $html = self::captureOutput(static fn() => wp_scripts()->do_items(['it-dependent']));

        $this->assertInert(self::tag($html, 'it-dependent-js'), 'analytics');
    }

    public function testScriptNeedingTwoCategoriesListsBoth(): void
    {
        $html = self::captureOutput(static fn() => wp_scripts()->do_items(['it-ads']));

        $this->assertStringContainsString('data-mortar-consent="analytics marketing"', $html);
    }

    public function testUnmappedScriptIsUntouched(): void
    {
        $html = self::captureOutput(static fn() => wp_scripts()->do_items(['it-plain']));

        $this->assertStringContainsString('src="https://cdn.example/p.js"', $html);
        $this->assertStringNotContainsString('text/plain', $html);
        $this->assertStringNotContainsString('data-mortar-consent', $html);
    }

    public function testOriginalTypeIsKeptForTheRuntime(): void
    {
        $attributes = apply_filters('wp_script_attributes', ['id' => 'it-tracker-js', 'type' => 'module', 'src' => 'https://tracker.example/t.js']);

        $this->assertSame('text/plain', $attributes['type']);
        $this->assertSame('module', $attributes['data-mortar-consent-type']);
    }

    private function assertInert(string $tag, string $categories): void
    {
        $this->assertStringContainsString('type="text/plain"', $tag);
        $this->assertStringContainsString('data-mortar-consent="' . $categories . '"', $tag);
    }

    /** The opening tag of the script with this id; WordPress sorts attributes, so match it as a whole. */
    private static function tag(string $html, string $id): string
    {
        preg_match('#<script\b[^>]*\bid="' . preg_quote($id, '#') . '"[^>]*>#', $html, $match);

        return $match[0] ?? '';
    }
}

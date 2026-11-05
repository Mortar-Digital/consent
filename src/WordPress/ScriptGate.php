<?php

declare(strict_types=1);

namespace MortarDigital\Consent\WordPress;

use MortarDigital\Consent\ScriptMap;

/**
 * Rewrites mapped script tags (and their inline before/after/extra data) to
 * `type="text/plain"` so browsers do not fetch or run them. The runtime
 * activates them once the visitor grants every listed category.
 *
 * Scripts that depend on a gated handle inherit its categories, otherwise
 * they would run and fail without their dependency.
 */
final class ScriptGate
{
    /** @var array<string, list<string>> */
    private array $resolved = [];

    public function __construct(private readonly ScriptMap $map) {}

    public function register(): void
    {
        add_filter('wp_script_attributes', $this->filterScript(...), 20);
        add_filter('wp_inline_script_attributes', $this->filterInlineScript(...), 20);
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function filterScript(array $attributes): array
    {
        $id = is_string($attributes['id'] ?? null) ? $attributes['id'] : '';

        if (preg_match('/^(.+)-js-module$/', $id, $m) === 1) {
            return $this->gate($attributes, $this->map->categoriesFor($m[1]));
        }

        if (preg_match('/^(.+)-js$/', $id, $m) === 1) {
            return $this->gate($attributes, $this->categoriesFor($m[1]));
        }

        return $attributes;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    public function filterInlineScript(array $attributes): array
    {
        $id = is_string($attributes['id'] ?? null) ? $attributes['id'] : '';

        if (preg_match('/^(.+)-js-(?:before|after|extra|translations)$/', $id, $m) !== 1) {
            return $attributes;
        }

        return $this->gate($attributes, $this->categoriesFor($m[1]));
    }

    /**
     * @param array<string, mixed> $attributes
     * @param list<string> $categories
     * @return array<string, mixed>
     */
    private function gate(array $attributes, array $categories): array
    {
        if ($categories === []) {
            return $attributes;
        }
        $type = $attributes['type'] ?? '';

        if (is_string($type) && $type !== '' && $type !== 'text/javascript') {
            $attributes['data-mortar-consent-type'] = $type;
        }
        $attributes['type'] = 'text/plain';
        $attributes['data-mortar-consent'] = implode(' ', $categories);

        return $attributes;
    }

    /** @return list<string> */
    private function categoriesFor(string $handle): array
    {
        return $this->resolve($handle, []);
    }

    /**
     * @param array<string, true> $visiting cycle guard
     * @return list<string>
     */
    private function resolve(string $handle, array $visiting): array
    {
        if (isset($this->resolved[$handle])) {
            return $this->resolved[$handle];
        }

        if (isset($visiting[$handle])) {
            return [];
        }
        $visiting[$handle] = true;

        $categories = $this->map->categoriesFor($handle);
        $registered = wp_scripts()->registered[$handle] ?? null;

        foreach ($registered->deps ?? [] as $dep) {
            if (is_string($dep)) {
                $categories = [...$categories, ...$this->resolve($dep, $visiting)];
            }
        }

        return $this->resolved[$handle] = array_values(array_unique($categories));
    }
}

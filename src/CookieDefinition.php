<?php

declare(strict_types=1);

namespace MortarDigital\Consent;

/**
 * One row of the cookie declaration. The name doubles as a deletion pattern:
 * a trailing `*` matches a prefix (`_ga_*`).
 */
final class CookieDefinition
{
    private const NAME_PATTERN = '/^[A-Za-z0-9_.\-]{1,100}\*?$/D';
    private const MAX_TEXT = 500;

    public function __construct(
        public readonly string $name,
        public readonly string $provider = '',
        public readonly string $purpose = '',
        public readonly string $duration = '',
    ) {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw new InvalidConfiguration(esc_html(sprintf(/* translators: %s: cookie name */ __('Invalid cookie name pattern "%s".', 'mortar-consent'), substr($name, 0, 100))));
        }
        foreach (['provider' => $provider, 'purpose' => $purpose, 'duration' => $duration] as $field => $value) {
            if (strlen($value) > self::MAX_TEXT) {
                throw new InvalidConfiguration(esc_html(sprintf(/* translators: 1: cookie name, 2: field name (provider, purpose or duration) */ __('Cookie "%1$s" %2$s is too long.', 'mortar-consent'), $name, $field)));
            }
        }
    }

    /** Accepts a bare name or `['name' => …, 'provider' => …, 'purpose' => …, 'duration' => …]`. */
    public static function from(mixed $data): self
    {
        if (is_string($data)) {
            return new self($data);
        }
        if (! is_array($data) || ! is_string($data['name'] ?? null)) {
            throw new InvalidConfiguration(esc_html__('Each cookie needs a string "name".', 'mortar-consent'));
        }
        $text = [];
        foreach (['provider', 'purpose', 'duration'] as $field) {
            $value = $data[$field] ?? '';
            if (! is_string($value)) {
                throw new InvalidConfiguration(esc_html(sprintf(/* translators: 1: cookie name, 2: field name (provider, purpose or duration) */ __('Cookie "%1$s" %2$s must be a string.', 'mortar-consent'), $data['name'], $field)));
            }
            $text[$field] = $value;
        }

        return new self($data['name'], $text['provider'], $text['purpose'], $text['duration']);
    }

    /** @return array{name: string, provider: string, purpose: string, duration: string} */
    public function toArray(): array
    {
        return ['name' => $this->name, 'provider' => $this->provider, 'purpose' => $this->purpose, 'duration' => $this->duration];
    }
}

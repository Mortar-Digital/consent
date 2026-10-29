<?php

declare(strict_types=1);

namespace MortarDigital\Consent;

/**
 * A consent purpose shown to visitors (e.g. "Analytics"). Required categories
 * cover strictly necessary storage: always active, never asked for.
 */
final class Category
{
    public const ID_PATTERN = '[a-z][a-z0-9_-]{0,31}';

    /** @var list<CookieDefinition> */
    public readonly array $cookies;

    /** @param list<mixed> $cookies names or cookie definition arrays */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $description,
        public readonly bool $required = false,
        array $cookies = [],
    ) {
        if (preg_match('/^' . self::ID_PATTERN . '$/D', $id) !== 1) {
            throw new InvalidConfiguration(esc_html(sprintf(/* translators: %s: category id */ __('Category id "%s" must be lowercase letters, digits, "_" or "-", starting with a letter (max 32).', 'mortar-consent'), $id)));
        }
        if (trim($label) === '' || strlen($label) > 200 || strlen($description) > 2000) {
            throw new InvalidConfiguration(esc_html(sprintf(/* translators: %s: category id */ __('Category "%s" needs a label (max 200 bytes) and a description under 2000 bytes.', 'mortar-consent'), $id)));
        }
        if (! array_is_list($cookies)) {
            throw new InvalidConfiguration(esc_html(sprintf(/* translators: %s: category id */ __('Category "%s" cookies must be a list.', 'mortar-consent'), $id)));
        }
        $this->cookies = array_map(
            static fn (mixed $c): CookieDefinition => $c instanceof CookieDefinition ? $c : CookieDefinition::from($c),
            $cookies,
        );
    }

    /** @return list<string> */
    public function cookieNames(): array
    {
        return array_map(static fn (CookieDefinition $c): string => $c->name, $this->cookies);
    }

    /** @return array{id: string, label: string, description: string, required: bool, cookies: list<array<string, string>>} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'description' => $this->description,
            'required' => $this->required,
            'cookies' => array_map(static fn (CookieDefinition $c): array => $c->toArray(), $this->cookies),
        ];
    }

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        foreach (['id', 'label'] as $key) {
            if (! isset($data[$key]) || ! is_string($data[$key])) {
                throw new InvalidConfiguration(esc_html(sprintf(/* translators: %s: field name (id or label) */ __('Each category needs a string "%s".', 'mortar-consent'), $key)));
            }
        }
        $description = $data['description'] ?? '';
        $required = $data['required'] ?? false;
        $cookies = $data['cookies'] ?? [];
        if (! is_string($description) || ! is_bool($required) || ! is_array($cookies) || ! array_is_list($cookies)) {
            throw new InvalidConfiguration(esc_html(sprintf(/* translators: %s: category id */ __('Category "%s" has an invalid description, required flag or cookie list.', 'mortar-consent'), $data['id'])));
        }

        return new self($data['id'], $data['label'], $description, $required, $cookies);
    }
}

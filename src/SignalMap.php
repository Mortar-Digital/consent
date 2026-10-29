<?php

declare(strict_types=1);

namespace MortarDigital\Consent;

/**
 * Maps third-party consent signals (Google Consent Mode, WP Consent API
 * categories) to our categories. A signal is granted only when it lists at
 * least one category and every listed category is allowed, so an empty or
 * emptied mapping fails closed.
 */
final class SignalMap
{
    /** @var array<string, list<string>> */
    private array $map = [];

    /**
     * @param array<mixed> $map signal => category id or list of ids
     * @param list<string> $signals the signals this map may define
     */
    public function __construct(array $map, CategoryRegistry $categories, array $signals, string $name)
    {
        foreach ($map as $signal => $ids) {
            if (! is_string($signal) || ! in_array($signal, $signals, true)) {
                throw new InvalidConfiguration(esc_html(sprintf(/* translators: 1: setting name, 2: signal name */ __('%1$s: unknown signal "%2$s".', 'mortar-consent'), $name, is_string($signal) ? $signal : (string) $signal)));
            }
            $ids = is_string($ids) ? [$ids] : $ids;
            if (! is_array($ids) || ! array_is_list($ids)) {
                throw new InvalidConfiguration(esc_html(sprintf(/* translators: 1: setting name, 2: signal name */ __('%1$s: "%2$s" must map to a category id or list of ids.', 'mortar-consent'), $name, $signal)));
            }
            foreach ($ids as $id) {
                if (! is_string($id) || ! $categories->has($id)) {
                    throw new InvalidConfiguration(esc_html(sprintf(/* translators: 1: setting name, 2: signal name, 3: category id */ __('%1$s: "%2$s" refers to unknown category "%3$s".', 'mortar-consent'), $name, $signal, is_string($id) ? $id : gettype($id))));
                }
            }
            $this->map[$signal] = array_values(array_unique($ids));
        }
    }

    /**
     * @param callable(string): bool $allows
     * @return array<string, bool>
     */
    public function resolve(callable $allows): array
    {
        return array_map(
            static fn (array $ids): bool => $ids !== [] && array_reduce($ids, static fn (bool $carry, string $id): bool => $carry && $allows($id), true),
            $this->map,
        );
    }

    /** @return array<string, list<string>> */
    public function toArray(): array
    {
        return $this->map;
    }
}

<?php

declare(strict_types=1);

namespace MortarDigital\Consent;

/** Maps registered script handles to the optional categories they need. */
final class ScriptMap
{
    /** @var array<string, list<string>> */
    private array $map = [];

    /** @param array<mixed> $map handle => category id or list of ids (all required) */
    public function __construct(array $map, CategoryRegistry $categories)
    {
        foreach ($map as $handle => $ids) {
            if (! is_string($handle) || $handle === '' || strlen($handle) > 200) {
                throw new InvalidConfiguration('Script handles must be non-empty strings.');
            }
            $ids = is_string($ids) ? [$ids] : $ids;
            if (! is_array($ids) || $ids === [] || ! array_is_list($ids)) {
                throw new InvalidConfiguration(esc_html(sprintf('Script "%s" must map to a category id or list of ids.', $handle)));
            }
            foreach ($ids as $id) {
                if (! is_string($id) || ! $categories->isOptional($id)) {
                    throw new InvalidConfiguration(esc_html(sprintf('Script "%s" maps to "%s", which is not an optional category.', $handle, is_string($id) ? $id : gettype($id))));
                }
            }
            $this->map[$handle] = array_values(array_unique($ids));
        }
    }

    /** @return list<string> */
    public function categoriesFor(string $handle): array
    {
        return $this->map[$handle] ?? [];
    }
}

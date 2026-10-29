<?php

declare(strict_types=1);

namespace MortarDigital\Consent;

/** Ordered, uniquely keyed set of categories. */
final class CategoryRegistry
{
    /** @var array<string, Category> */
    private array $categories = [];

    public function __construct(Category ...$categories)
    {
        if ($categories === []) {
            throw new InvalidConfiguration('At least one category is required.');
        }
        foreach ($categories as $category) {
            if (isset($this->categories[$category->id])) {
                throw new InvalidConfiguration(sprintf('Duplicate category id "%s".', $category->id));
            }
            $this->categories[$category->id] = $category;
        }
    }

    public function has(string $id): bool
    {
        return isset($this->categories[$id]);
    }

    public function get(string $id): Category
    {
        return $this->categories[$id] ?? throw new InvalidConfiguration(sprintf('Unknown category "%s".', $id));
    }

    public function isOptional(string $id): bool
    {
        return isset($this->categories[$id]) && ! $this->categories[$id]->required;
    }

    /** @return list<Category> */
    public function all(): array
    {
        return array_values($this->categories);
    }

    /** @return list<string> */
    public function optionalIds(): array
    {
        return array_keys(array_filter($this->categories, static fn (Category $c): bool => ! $c->required));
    }
}

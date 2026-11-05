<?php

declare(strict_types=1);

namespace MortarDigital\Consent;

/** A visitor's recorded choice. Only granted optional categories are stored. */
final class ConsentState
{
    public const UUID_PATTERN = '[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}';

    /** @var list<string> */
    public readonly array $granted;

    /** @param list<string> $granted */
    public function __construct(
        public readonly int $policyVersion,
        public readonly int $timestamp,
        public readonly string $consentId,
        array $granted,
    ) {
        if ($policyVersion < 1 || $timestamp < 0) {
            throw new \InvalidArgumentException('Policy version must be positive and timestamp non-negative.');
        }

        if (preg_match('/^' . self::UUID_PATTERN . '$/D', $consentId) !== 1) {
            throw new \InvalidArgumentException('Consent id must be a lowercase UUIDv4.');
        }
        $this->granted = array_values(array_unique($granted));
    }

    public function grants(string $categoryId): bool
    {
        return in_array($categoryId, $this->granted, true);
    }
}

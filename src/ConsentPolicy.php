<?php

declare(strict_types=1);

namespace MortarDigital\Consent;

/**
 * Decides whether a stored choice still counts. Consent lapses when the policy
 * version is bumped or the lifetime passes, and visitors are asked again.
 */
final class ConsentPolicy
{
    /** CNIL's recommended ceiling of 13 months; ICO sets none but expects renewal. */
    public const MAX_LIFETIME_DAYS = 395;

    public function __construct(
        public readonly CategoryRegistry $categories,
        public readonly int $version,
        public readonly int $lifetimeDays,
    ) {
        if ($version < 1) {
            throw new InvalidConfiguration(esc_html__('Policy version must be a positive integer.', 'mortar-consent'));
        }
        if ($lifetimeDays < 1 || $lifetimeDays > self::MAX_LIFETIME_DAYS) {
            throw new InvalidConfiguration(esc_html(sprintf(/* translators: %d: maximum number of days */ __('Consent lifetime must be between 1 and %d days.', 'mortar-consent'), self::MAX_LIFETIME_DAYS)));
        }
    }

    public function lifetimeSeconds(): int
    {
        return $this->lifetimeDays * 86400;
    }

    public function isCurrent(ConsentState $state, int $now): bool
    {
        return $state->policyVersion === $this->version
            && $state->timestamp <= $now + 300 // tolerate small clock skew from the visitor's device
            && $now - $state->timestamp <= $this->lifetimeSeconds();
    }

    public function allows(?ConsentState $state, string $categoryId, int $now): bool
    {
        if (! $this->categories->has($categoryId)) {
            return false;
        }
        if (! $this->categories->isOptional($categoryId)) {
            return true;
        }

        return $state !== null && $this->isCurrent($state, $now) && $state->grants($categoryId);
    }
}

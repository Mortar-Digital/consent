<?php

declare(strict_types=1);

namespace MortarDigital\Consent;

/**
 * Proof that a choice was made: which categories, under which policy version,
 * how, and when. Deliberately holds no IP address, user agent or user id.
 */
final class ConsentRecord
{
    public const METHODS = ['accept-all', 'reject-all', 'custom'];

    /** @param list<string> $granted */
    private function __construct(
        public readonly string $consentId,
        public readonly int $policyVersion,
        public readonly array $granted,
        public readonly string $method,
        public readonly int $timestamp,
    ) {}

    /**
     * Validates an untrusted payload from the browser. The policy version must
     * be the current one, so records cannot be back-dated to an old policy.
     *
     * @param array<mixed> $payload
     */
    public static function fromPayload(array $payload, ConsentPolicy $policy, int $now): self
    {
        $allowed = ['consentId', 'policyVersion', 'granted', 'method'];

        if (array_diff(array_keys($payload), $allowed) !== []) {
            throw new InvalidRecord('Unexpected fields.');
        }
        $id = $payload['consentId'] ?? null;

        if (! is_string($id) || preg_match('/^' . ConsentState::UUID_PATTERN . '$/D', $id) !== 1) {
            throw new InvalidRecord('Invalid consent id.');
        }

        if (($payload['policyVersion'] ?? null) !== $policy->version) {
            throw new InvalidRecord('Stale policy version.');
        }
        $granted = $payload['granted'] ?? null;

        if (! is_array($granted) || ! array_is_list($granted) || count($granted) > 32) {
            throw new InvalidRecord('Invalid categories.');
        }

        foreach ($granted as $categoryId) {
            if (! is_string($categoryId) || ! $policy->categories->isOptional($categoryId)) {
                throw new InvalidRecord('Unknown category.');
            }
        }
        $method = $payload['method'] ?? null;

        if (! in_array($method, self::METHODS, true)) {
            throw new InvalidRecord('Invalid method.');
        }
        // Normalise to configured order so equal choices store identically.
        $granted = array_values(array_intersect($policy->categories->optionalIds(), $granted));

        return new self($id, $policy->version, $granted, $method, $now);
    }
}

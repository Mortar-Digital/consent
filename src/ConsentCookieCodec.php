<?php

declare(strict_types=1);

namespace MortarDigital\Consent;

/**
 * Compact, cookie-safe consent format shared with assets/consent.js:
 *
 *     1|<policy version>|<unix time>|<uuid>|<granted ids joined by ".">
 *
 * The cookie is visitor-controlled, so decoding is strict and fails closed:
 * anything malformed reads as "no consent".
 */
final class ConsentCookieCodec
{
    public const COOKIE_NAME = 'mortar_consent';
    public const SCHEMA = 1;
    private const MAX_LENGTH = 1024;

    /**
     * The full-value regex without delimiters. It is valid in both PCRE and
     * JavaScript, so the head snippet for Consent Mode can reuse it.
     */
    public static function pattern(): string
    {
        return sprintf(
            '^%d\\|([1-9][0-9]{0,8})\\|([0-9]{1,12})\\|(%s)\\|((?:%s)(?:\\.%s)*)?$',
            self::SCHEMA,
            ConsentState::UUID_PATTERN,
            Category::ID_PATTERN,
            Category::ID_PATTERN,
        );
    }

    public function encode(ConsentState $state): string
    {
        return implode('|', [
            self::SCHEMA,
            $state->policyVersion,
            $state->timestamp,
            $state->consentId,
            implode('.', $state->granted),
        ]);
    }

    public function decode(string $value, CategoryRegistry $categories): ?ConsentState
    {
        if (strlen($value) > self::MAX_LENGTH) {
            return null;
        }
        $pattern = '/' . self::pattern() . '/D';
        if (preg_match($pattern, $value, $m) !== 1) {
            return null;
        }
        $granted = ($m[4] ?? '') === '' ? [] : explode('.', $m[4]);

        return new ConsentState(
            (int) $m[1],
            (int) $m[2],
            $m[3],
            array_values(array_filter($granted, $categories->isOptional(...))),
        );
    }
}

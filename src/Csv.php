<?php

declare(strict_types=1);

namespace MortarDigital\Consent;

/** CSV rows safe to open in spreadsheet software. */
final class Csv
{
    /** @param list<scalar|null> $fields */
    public static function row(array $fields): string
    {
        return implode(',', array_map(self::field(...), $fields)) . "\r\n";
    }

    /** @param scalar|null $value */
    private static function field(mixed $value): string
    {
        $value = (string) $value;
        // Neutralise formula injection (=, +, -, @, tab, CR) per OWASP guidance.
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            $value = "'" . $value;
        }

        return '"' . str_replace('"', '""', $value) . '"';
    }
}

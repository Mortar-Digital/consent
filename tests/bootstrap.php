<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

// The framework-agnostic classes translate and escape exception messages;
// unit tests run without WordPress, so stand in for those functions.
if (! function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }

    function esc_html__(string $text, string $domain = 'default'): string
    {
        return esc_html($text);
    }
}

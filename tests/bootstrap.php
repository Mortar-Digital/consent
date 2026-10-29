<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

// The framework-agnostic classes escape exception messages with esc_html();
// unit tests run without WordPress, so stand in for it.
if (! function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

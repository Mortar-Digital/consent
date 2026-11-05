<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

// PER coding style, plus the house rules: a blank line before control
// statements and returns, tidy imports and trailing commas in multi-line lists.
return (new Config())
    ->setRules([
        '@PER-CS' => true,
        'blank_line_before_statement' => [
            'statements' => ['break', 'continue', 'declare', 'do', 'for', 'foreach', 'if', 'return', 'switch', 'throw', 'try', 'while'],
        ],
        'no_extra_blank_lines' => [
            'tokens' => ['curly_brace_block', 'extra', 'parenthesis_brace_block', 'square_brace_block', 'throw', 'use'],
        ],
        'no_unused_imports' => true,
        'ordered_imports' => ['imports_order' => ['class', 'function', 'const'], 'sort_algorithm' => 'alpha'],
        'single_quote' => true,
        'not_operator_with_successor_space' => true,
        'trailing_comma_in_multiline' => ['elements' => ['arguments', 'arrays', 'match', 'parameters']],
        'phpdoc_trim' => true,
        'phpdoc_scalar' => true,
        'no_empty_phpdoc' => true,
    ])
    ->setFinder(Finder::create()
        ->in([__DIR__ . '/src', __DIR__ . '/tests'])
        ->append([__FILE__, __DIR__ . '/mortar-consent.php', __DIR__ . '/uninstall.php', __DIR__ . '/functions.php', __DIR__ . '/bin/lint']));

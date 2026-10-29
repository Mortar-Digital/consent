<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return (new Config())
    ->setRules(['@PSR12' => true])
    ->setFinder(Finder::create()
        ->in([__DIR__ . '/src', __DIR__ . '/plugin', __DIR__ . '/tests'])
        ->append([__FILE__, __DIR__ . '/functions.php', __DIR__ . '/bin/lint']));

<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in(array_filter([__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/e2e', __DIR__ . '/examples'], 'is_dir'));

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS2.0' => true,
        'declare_strict_types' => true,
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'single_quote' => true,
        'trailing_comma_in_multiline' => true,
    ])
    ->setFinder($finder);

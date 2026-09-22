<?php

declare(strict_types=1);

// padrão de estilo do projeto: base PSR-12 com alguns ajustes de higiene
$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests', __DIR__ . '/bin', __DIR__ . '/config', __DIR__ . '/public'])
    ->append([__FILE__]);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'trailing_comma_in_multiline' => true,
        'single_quote' => true,
        'blank_line_after_opening_tag' => true,
        'no_extra_blank_lines' => true,
    ])
    ->setFinder($finder);

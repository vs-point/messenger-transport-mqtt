<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\Operator\NotOperatorWithSuccessorSpaceFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withPaths([__DIR__ . '/src'])
    ->withPreparedSets(
        psr12: true,
        common: true,
        symplify: true,
        cleanCode: true,
    )
    ->withSkip([
        NotOperatorWithSuccessorSpaceFixer::class,
    ]);

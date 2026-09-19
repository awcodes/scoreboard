<?php

declare(strict_types=1);

use Pest\Rector\Set\PestSetList;
use Rector\Config\RectorConfig;
use Rector\Exception\Configuration\InvalidConfigurationException;

try {
    return RectorConfig::configure()
        ->withPaths([
            __DIR__.'/app',
            __DIR__.'/bootstrap/app.php',
            __DIR__.'/config',
            __DIR__.'/database',
            __DIR__.'/public',
            __DIR__.'/tests',
        ])
        ->withPreparedSets(
            deadCode: true,
            codeQuality: true,
            typeDeclarations: true,
            privatization: true,
            earlyReturn: true,
        )
        ->withSets([
            PestSetList::CODING_STYLE,
        ])
        ->withPhpSets();
} catch (InvalidConfigurationException $e) {
    // Handle the exception as needed, for example, log the error or display a message.
    echo 'Rector configuration error: '.$e->getMessage();
    exit(1);
}

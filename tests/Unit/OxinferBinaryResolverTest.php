<?php

declare(strict_types=1);

use Oxhq\Oxcribe\Support\OxinferBinaryResolver;

it('resolves an absolute windows binary path without prefixing the project root', function () {
    if (PHP_OS_FAMILY !== 'Windows') {
        test()->markTestSkipped('Windows-specific resolver behavior');
    }

    $projectRoot = sys_get_temp_dir().'\\oxcribe-resolver-project-'.bin2hex(random_bytes(4));
    $binaryPath = makePortablePhpCommand(
        $projectRoot.'\\external-bin',
        'oxinfer',
        <<<'PHP'
fwrite(STDOUT, "oxinfer 0.1.1\n");
PHP
    );

    $resolved = (new OxinferBinaryResolver)->resolve(
        ['binary' => $binaryPath],
        $projectRoot
    );

    expect($resolved)->toBe($binaryPath);
});

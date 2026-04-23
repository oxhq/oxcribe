<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Oxhq\Oxcribe\Support\PackageVersion;

it('reports a healthy first-publish preflight when the project, binary, and cloud config are ready', function () {
    $projectRoot = sys_get_temp_dir().'/oxcribe-doctor-project-'.bin2hex(random_bytes(6));
    $binaryPath = makePortablePhpCommand(
        $projectRoot.'/bin',
        'oxinfer',
        <<<'PHP'
fwrite(STDOUT, "oxinfer version 0.1.1\n");
PHP
    );

    File::put($projectRoot.'/composer.json', json_encode(['name' => 'acme/example'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    config()->set('app.version', '2026.03.26');
    config()->set('oxcribe.oxinfer.binary', $binaryPath);
    config()->set('oxcribe.publish.base_url', 'https://oxcloud.example.test');
    config()->set('oxcribe.publish.token', 'tok_test_publish_123456');
    config()->set('oxcribe.docs.enabled', true);
    config()->set('oxcribe.docs.route', 'oxcribe/docs');

    $this->artisan('oxcribe:doctor', ['--project-root' => $projectRoot])
        ->expectsOutputToContain('PASS  Project root:')
        ->expectsOutputToContain('PASS  composer.json:')
        ->expectsOutputToContain('PASS  Local docs: Local viewer enabled at /oxcribe/docs')
        ->expectsOutputToContain('PASS  Oxinfer binary:')
        ->expectsOutputToContain('PASS  Oxinfer version: oxinfer version 0.1.1')
        ->expectsOutputToContain('PASS  Oxcribe Cloud URL: https://oxcloud.example.test')
        ->expectsOutputToContain('PASS  Publish token: Configured')
        ->expectsOutputToContain('PASS  Resolved publish version: 2026.03.26')
        ->expectsOutputToContain('Oxcribe is ready for the next step.')
        ->assertSuccessful();

    File::deleteDirectory($projectRoot);
});

it('supports local-only readiness checks when cloud checks are skipped', function () {
    $projectRoot = sys_get_temp_dir().'/oxcribe-doctor-local-'.bin2hex(random_bytes(6));
    $binaryPath = makePortablePhpCommand(
        $projectRoot.'/bin',
        'oxinfer',
        <<<'PHP'
fwrite(STDOUT, "oxinfer version 0.1.1\n");
PHP
    );

    File::put($projectRoot.'/composer.json', json_encode(['name' => 'acme/local'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    config()->set('oxcribe.oxinfer.binary', $binaryPath);
    config()->set('oxcribe.publish.base_url', null);
    config()->set('oxcribe.publish.token', null);
    config()->set('oxcribe.docs.enabled', false);

    $this->artisan('oxcribe:doctor', ['--project-root' => $projectRoot, '--skip-cloud' => true])
        ->expectsOutputToContain('WARN  Local docs: Local viewer is disabled.')
        ->expectsOutputToContain('WARN  Oxcribe Cloud: Skipped with --skip-cloud.')
        ->expectsOutputToContain('Oxcribe is ready for the next step.')
        ->assertSuccessful();

    File::deleteDirectory($projectRoot);
});

it('fails with actionable output when first-publish preflight is missing critical requirements', function () {
    $projectRoot = sys_get_temp_dir().'/oxcribe-doctor-broken-'.bin2hex(random_bytes(6));

    File::ensureDirectoryExists($projectRoot);
    File::put($projectRoot.'/composer.json', json_encode(['name' => 'acme/broken'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    config()->set('oxcribe.oxinfer.binary', $projectRoot.'/bin/missing-oxinfer');
    config()->set('oxcribe.oxinfer.source_root', null);
    config()->set('oxcribe.publish.base_url', 'https://oxcloud.example.test');
    config()->set('oxcribe.publish.token', null);

    $this->artisan('oxcribe:doctor', ['--project-root' => $projectRoot])
        ->expectsOutputToContain('FAIL  Oxinfer binary:')
        ->expectsOutputToContain(sprintf('Next: run `php artisan oxcribe:install-binary %s`', PackageVersion::TAG))
        ->expectsOutputToContain('FAIL  Publish token: Missing OXCLOUD_TOKEN / oxcribe.publish.token.')
        ->expectsOutputToContain('Oxcribe preflight found blocking issues.')
        ->assertFailed();

    File::deleteDirectory($projectRoot);
});

it('suggests the configured local source root when oxinfer is missing', function () {
    $projectRoot = sys_get_temp_dir().'/oxcribe-doctor-source-'.bin2hex(random_bytes(6));

    File::ensureDirectoryExists($projectRoot);
    File::put($projectRoot.'/composer.json', json_encode(['name' => 'acme/source'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    config()->set('oxcribe.oxinfer.binary', $projectRoot.'/bin/missing-oxinfer');
    config()->set('oxcribe.oxinfer.source_root', '/tmp/oxinfer-source');
    config()->set('oxcribe.publish.base_url', null);
    config()->set('oxcribe.publish.token', null);

    $this->artisan('oxcribe:doctor', ['--project-root' => $projectRoot])
        ->expectsOutputToContain(sprintf(
            'php artisan oxcribe:install-binary %s --source-root=/tmp/oxinfer-source',
            PackageVersion::TAG,
        ))
        ->assertFailed();

    File::deleteDirectory($projectRoot);
});

<?php

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

function productionAsset(string $path): string
{
    return file_get_contents(base_path($path)) ?: '';
}

/**
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function installerEnvironment(string $state, array $overrides = []): array
{
    return array_replace([
        'PATH' => base_path('tests/Fixtures/installer').PATH_SEPARATOR.getenv('PATH'),
        'KOAKADEMY_INSTALLER_TEST_STATE' => $state,
        'KOAKADEMY_INSTALLER_TEST_RELEASE_TAG' => 'v1.16.2',
        'KOAKADEMY_VERSION' => 'v1.16.2',
        'KOAKADEMY_INSTALLER_TEST_IMAGE' => 'ghcr.io/yukazakiri/koakademy@sha256:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'KOAKADEMY_DOMAIN' => 'school.example',
    ], $overrides);
}

it('documents the Linux release-backed one-line installer', function (): void {
    $command = 'curl -fsSL https://github.com/yukazakiri/koakademy/releases/latest/download/install.sh | sudo bash -s -- install --domain school.example';

    expect(productionAsset('README.md'))
        ->toContain($command)
        ->and(productionAsset('GETTING_STARTED.md'))
        ->toContain($command)
        ->not->toContain('install.ps1');
});

it('keeps the bootstrap focused on a checksummed stable release command', function (): void {
    $bootstrap = productionAsset('scripts/install.sh');
    $operator = productionAsset('scripts/koakademy');

    expect($bootstrap)
        ->toContain('releases/download/$'.'{tag}/koakademy')
        ->toContain('sha256sum --check')
        ->toContain('exact stable vX.Y.Z release tag')
        ->and($operator)
        ->toContain('https://get.docker.com')
        ->toContain('docker stack deploy')
        ->toContain('sha256sum --check')
        ->toContain('immutable GHCR digest')
        ->toContain('koakademy configure storage')
        ->toContain('koakademy configure mail')
        ->toContain('koakademy configure search')
        ->toContain('Application image rolled back. Database migrations were not reversed.')
        ->not->toContain('ghcr.io/yukazakiri/koakademy:latest')
        ->not->toContain('KOAKADEMY_VERSION=edge')
        ->not->toContain('docker swarm leave');
});

it('keeps Caddy public and the application private on the Swarm overlay', function (): void {
    $stack = productionAsset('scripts/swarm-stack.yml');
    $caddyfile = productionAsset('scripts/Caddyfile');

    expect($stack)
        ->toContain('published: 80')
        ->toContain('published: 443')
        ->toContain('APP_REPLICAS')
        ->toContain('driver: overlay')
        ->toContain('attachable: true')
        ->not->toContain('published: 8000')
        ->and($caddyfile)
        ->toContain('reverse_proxy app:8000');
});

it('parses all release installer shell assets', function (): void {
    $bash = (new ExecutableFinder)->find('bash');
    $sh = (new ExecutableFinder)->find('sh');

    if ($bash === null || $sh === null) {
        $this->markTestSkipped('Shell executables are unavailable.');
    }

    foreach ([
        [$bash, '-n', base_path('scripts/install.sh')],
        [$bash, '-n', base_path('scripts/koakademy')],
        [$sh, '-n', base_path('scripts/koakademy-app-entrypoint.sh')],
    ] as $command) {
        $process = new Process($command, base_path());
        $process->run();

        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput().$process->getOutput());
    }
});

it('installs a Caddy-backed Swarm release through the fixture Docker engine', function (): void {
    $bash = (new ExecutableFinder)->find('bash');

    if ($bash === null || DIRECTORY_SEPARATOR === '\\') {
        $this->markTestSkipped('The installer fixture requires Bash on a Unix-like host.');
    }

    $filesystem = new Filesystem;
    $state = storage_path('framework/testing/installer-'.bin2hex(random_bytes(6)));
    $filesystem->mkdir($state);

    try {
        $environment = installerEnvironment($state);
        $process = new Process([$bash, base_path('scripts/install.sh'), 'install', '--domain', 'school.example'], base_path(), $environment);
        $process->setTimeout(30);
        $process->run();

        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput().$process->getOutput());

        $log = file_get_contents($state.'/docker.log') ?: '';
        $runtime = file_get_contents($state.'/runtime/runtime.env') ?: '';
        expect($log)
            ->toContain('stack deploy')
            ->toContain('service create --name koakademy-migrate-')
            ->and($runtime)
            ->toContain('APP_REPLICAS=1')
            ->toContain('FILESYSTEM_DISK=public')
            ->toContain('KOAKADEMY_IMAGE=ghcr.io/yukazakiri/koakademy@sha256:');
    } finally {
        $filesystem->remove($state);
    }
});

it('backs up, migrates, and records the prior immutable image during an update', function (): void {
    $bash = (new ExecutableFinder)->find('bash');

    if ($bash === null || DIRECTORY_SEPARATOR === '\\') {
        $this->markTestSkipped('The installer fixture requires Bash on a Unix-like host.');
    }

    $filesystem = new Filesystem;
    $state = storage_path('framework/testing/installer-update-'.bin2hex(random_bytes(6)));
    $filesystem->mkdir($state);

    try {
        $environment = installerEnvironment($state);
        $install = new Process([$bash, base_path('scripts/install.sh'), 'install', '--domain', 'school.example'], base_path(), $environment);
        $install->setTimeout(30);
        $install->run();
        expect($install->isSuccessful())->toBeTrue($install->getErrorOutput().$install->getOutput());

        $environment['KOAKADEMY_VERSION'] = 'v1.16.3';
        $environment['KOAKADEMY_INSTALLER_TEST_RELEASE_TAG'] = 'v1.16.3';
        $environment['KOAKADEMY_INSTALLER_TEST_IMAGE'] = 'ghcr.io/yukazakiri/koakademy@sha256:bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
        $update = new Process([$bash, base_path('scripts/koakademy'), 'update', '--release', 'v1.16.3'], base_path(), $environment);
        $update->setTimeout(30);
        $update->run();

        expect($update->isSuccessful())->toBeTrue($update->getErrorOutput().$update->getOutput());

        $runtime = file_get_contents($state.'/runtime/runtime.env') ?: '';
        $backups = glob($state.'/runtime/backups/*.dump');
        expect($runtime)
            ->toContain('KOAKADEMY_IMAGE=ghcr.io/yukazakiri/koakademy@sha256:bbbb')
            ->toContain('PREVIOUS_KOAKADEMY_IMAGE=ghcr.io/yukazakiri/koakademy@sha256:aaaaaaaa')
            ->and($backups)
            ->not->toBeEmpty();
    } finally {
        $filesystem->remove($state);
    }
});

it('initializes an inactive Swarm, but fails before deployment on unsafe hosts', function (): void {
    $bash = (new ExecutableFinder)->find('bash');

    if ($bash === null || DIRECTORY_SEPARATOR === '\\') {
        $this->markTestSkipped('The installer fixture requires Bash on a Unix-like host.');
    }

    $filesystem = new Filesystem;
    $state = storage_path('framework/testing/installer-safety-'.bin2hex(random_bytes(6)));
    $filesystem->mkdir($state);

    try {
        $inactive = new Process([$bash, base_path('scripts/install.sh'), 'install', '--domain', 'school.example'], base_path(), installerEnvironment($state, [
            'KOAKADEMY_INSTALLER_TEST_SWARM_STATE' => 'inactive',
        ]));
        $inactive->run();
        expect($inactive->isSuccessful())->toBeTrue($inactive->getErrorOutput().$inactive->getOutput())
            ->and(file_get_contents($state.'/docker.log'))
            ->toContain('swarm init');

        $legacyState = $state.'-legacy';
        $filesystem->mkdir($legacyState);
        $legacy = new Process([$bash, base_path('scripts/install.sh'), 'install', '--domain', 'school.example'], base_path(), installerEnvironment($legacyState, [
            'KOAKADEMY_INSTALLER_TEST_LEGACY_SERVICE' => 'koakademy-app',
        ]));
        $legacy->run();
        expect($legacy->isSuccessful())->toBeFalse()
            ->and($legacy->getErrorOutput())
            ->toContain('Legacy service koakademy-app was detected')
            ->and(file_get_contents($legacyState.'/docker.log'))
            ->not->toContain('stack deploy');

        $managerState = $state.'-manager';
        $filesystem->mkdir($managerState);
        $manager = new Process([$bash, base_path('scripts/install.sh'), 'install', '--domain', 'school.example'], base_path(), installerEnvironment($managerState, [
            'KOAKADEMY_INSTALLER_TEST_SWARM_MANAGER' => 'false',
        ]));
        $manager->run();
        expect($manager->isSuccessful())->toBeFalse()
            ->and($manager->getErrorOutput())
            ->toContain('Docker Swarm manager node')
            ->and(file_get_contents($managerState.'/docker.log'))
            ->not->toContain('stack deploy');

        $portState = $state.'-port';
        $filesystem->mkdir($portState);
        $port = new Process([$bash, base_path('scripts/install.sh'), 'install', '--domain', 'school.example'], base_path(), installerEnvironment($portState, [
            'KOAKADEMY_INSTALLER_TEST_PORT_CONFLICT' => '443',
        ]));
        $port->run();
        expect($port->isSuccessful())->toBeFalse()
            ->and($port->getErrorOutput())
            ->toContain('Port 443 is already in use')
            ->and(file_get_contents($portState.'/docker.log'))
            ->not->toContain('stack deploy');
    } finally {
        $filesystem->remove([$state, $state.'-legacy', $state.'-manager', $state.'-port']);
    }
});

it('is idempotent and rotates provider secrets without leaking their values', function (): void {
    $bash = (new ExecutableFinder)->find('bash');

    if ($bash === null || DIRECTORY_SEPARATOR === '\\') {
        $this->markTestSkipped('The installer fixture requires Bash on a Unix-like host.');
    }

    $filesystem = new Filesystem;
    $state = storage_path('framework/testing/installer-providers-'.bin2hex(random_bytes(6)));
    $filesystem->mkdir($state);

    try {
        $environment = installerEnvironment($state);
        $install = new Process([$bash, base_path('scripts/install.sh'), 'install', '--domain', 'school.example'], base_path(), $environment);
        $install->run();
        expect($install->isSuccessful())->toBeTrue($install->getErrorOutput().$install->getOutput());

        $logBeforeRepeat = file_get_contents($state.'/docker.log') ?: '';
        $repeat = new Process([$bash, base_path('scripts/koakademy'), 'install', '--domain', 'school.example'], base_path(), $environment);
        $repeat->run();
        expect($repeat->isSuccessful())->toBeFalse()
            ->and($repeat->getErrorOutput())
            ->toContain('already installed')
            ->and(file_get_contents($state.'/docker.log'))
            ->toBe($logBeforeRepeat);

        $before = file_get_contents($state.'/runtime/runtime.env') ?: '';
        preg_match('/S3_SECRET_KEY_SECRET=([^\\n]+)/', $before, $matches);
        $previousSecretName = $matches[1] ?? '';

        $storage = new Process([$bash, base_path('scripts/koakademy'), 'configure', 'storage', 'r2'], base_path(), installerEnvironment($state, [
            'KOAKADEMY_S3_ENDPOINT' => 'https://account-id.r2.cloudflarestorage.com',
            'KOAKADEMY_S3_BUCKET' => 'koakademy-uploads',
            'KOAKADEMY_S3_PUBLIC_URL' => '',
            'KOAKADEMY_S3_ACCESS_KEY' => 'r2-access-key',
            'KOAKADEMY_S3_SECRET_KEY' => 'r2-secret-value',
        ]));
        $storage->run();
        expect($storage->isSuccessful())->toBeTrue($storage->getErrorOutput().$storage->getOutput());

        $after = file_get_contents($state.'/runtime/runtime.env') ?: '';
        $log = file_get_contents($state.'/docker.log') ?: '';
        expect($after)
            ->toContain('FILESYSTEM_DISK=s3')
            ->not->toContain('r2-secret-value')
            ->and($after)
            ->not->toContain("S3_SECRET_KEY_SECRET={$previousSecretName}")
            ->and($log)
            ->toContain('stack deploy')
            ->not->toContain('r2-secret-value')
            ->not->toContain('r2-access-key');
    } finally {
        $filesystem->remove($state);
    }
});

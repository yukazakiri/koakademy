<?php

declare(strict_types=1);

use App\Modules\Contracts\ModuleManifest;
use App\Modules\Contracts\ModuleRelease;
use App\Modules\Contracts\ModuleRequirements;
use App\Modules\VersionConstraint;

it('parses module dependencies identically in manifests and catalog releases', function (): void {
    $requires = ['core' => '>=1.22.0', 'php' => '>=8.5 <8.6', 'modules' => ['Forms' => '^1.10']];
    $manifest = ModuleManifest::fromArray([
        'name' => 'Surveys', 'alias' => 'surveys', 'version' => '1.0.0', 'license' => 'AGPL-3.0-or-later',
        'requires' => $requires, 'providers' => ['Example\\Provider'],
    ]);
    $release = ModuleRelease::fromArray([
        'version' => '1.0.0', 'asset_url' => 'https://example.test/surveys.zip', 'sha256' => str_repeat('a', 64),
        'requires' => $requires,
    ]);

    expect($release->requires)->toBe($manifest->requires)->toBe($requires);
});

it('rejects malformed dependency requirements', function (mixed $requirements): void {
    expect(fn () => ModuleRequirements::fromArray($requirements))->toThrow(InvalidArgumentException::class);
})->with([
    'non-object' => [false],
    'invalid constraint' => [['core' => 'not-a-version']],
    'invalid dependencies' => [['modules' => 'Forms']],
    'invalid dependency name' => [['modules' => ['../Forms' => '^1.0']]],
    'unknown requirement' => [['phpp' => '>=8.5']],
    'nested dependency constraint' => [['modules' => ['Forms' => ['^1.0']]]],
]);

it('uses Composer constraint semantics and preserves the edge core policy', function (string $version, string $constraint, bool $matches): void {
    expect((new VersionConstraint)->matches($version, $constraint))->toBe($matches);
})->with([
    ['8.5.12', '>=8.5 <8.6', true],
    ['8.6.0', '>=8.5 <8.6', false],
    ['1.8.0', '~1.4', true],
    ['1.5.0', '~1.4.2', false],
    ['1.2.4', '1.2.*', true],
    ['1.22.0-edge+sha.abcdef', '>=1.22.0', true],
    ['1.21.9-edge+sha.abcdef', '>=1.22.0', false],
    ['1.0.0-beta.1', '>=1.0.0', true],
    ['1.0.0-beta.1', '>=1.0.0-stable', false],
    ['1.0.0', 'this is invalid', false],
]);

it('rejects catalog versions that cannot form a safe exact Composer requirement', function (): void {
    expect(fn () => ModuleRelease::fromArray([
        'version' => '1.0.0 --other-option', 'asset_url' => 'https://example.test/module.zip', 'sha256' => str_repeat('a', 64),
    ]))->toThrow(InvalidArgumentException::class);
});

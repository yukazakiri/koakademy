<?php

declare(strict_types=1);

use App\Modules\Contracts\ModuleManifest;
use App\Modules\ModuleStateRepository;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

function moduleStateManifest(string $name = 'Forms'): ModuleManifest
{
    return ModuleManifest::fromArray([
        'name' => $name,
        'alias' => mb_strtolower($name),
        'version' => '1.0.0',
        'description' => 'Test module.',
        'author' => 'KoAkademy contributors',
        'license' => 'AGPL-3.0-or-later',
        'requires' => [],
        'compatibility' => [],
        'providers' => ["Modules\\{$name}\\Providers\\{$name}ServiceProvider"],
        'composer_package' => 'koakademy/'.mb_strtolower($name),
    ]);
}

it('imports legacy statuses without overwriting existing database choices', function (): void {
    $statusesPath = storage_path('framework/testing/module-state-import-'.uniqid().'.json');

    try {
        config(['modules.statuses-file' => $statusesPath]);
        File::put($statusesPath, json_encode(['Forms' => true], JSON_THROW_ON_ERROR));

        $states = new ModuleStateRepository;
        $manifest = moduleStateManifest();

        expect($states->sync([$manifest], 'preserve'))->toBe(1)
            ->and((bool) DB::table('module_installations')->where('module_name', 'Forms')->value('enabled'))->toBeTrue();

        DB::table('module_installations')->where('module_name', 'Forms')->update(['enabled' => false]);

        $states->sync([$manifest], 'disabled');

        expect((bool) DB::table('module_installations')->where('module_name', 'Forms')->value('enabled'))->toBeFalse();
    } finally {
        File::delete($statusesPath);
    }
});

it('persists activation changes in the database and legacy file', function (): void {
    $statusesPath = storage_path('framework/testing/module-state-write-'.uniqid().'.json');

    try {
        config(['modules.statuses-file' => $statusesPath]);
        File::put($statusesPath, json_encode(['Forms' => false], JSON_THROW_ON_ERROR));

        $states = new ModuleStateRepository;
        $states->setEnabled('Forms', true);

        expect($states->isEnabled('Forms'))->toBeTrue()
            ->and((bool) DB::table('module_installations')->where('module_name', 'Forms')->value('enabled'))->toBeTrue()
            ->and((bool) DB::table('module_installations')->where('module_name', 'Forms')->value('restart_required'))->toBeTrue()
            ->and(json_decode(File::get($statusesPath), true, 512, JSON_THROW_ON_ERROR)['Forms'])->toBeTrue();
    } finally {
        File::delete($statusesPath);
    }
});

it('merges a fresh legacy status snapshot when a worker has stale state', function (): void {
    $statusesPath = storage_path('framework/testing/module-state-stale-worker-'.uniqid().'.json');

    try {
        config([
            'modules.activator' => 'file',
            'modules.statuses-file' => $statusesPath,
        ]);
        File::put($statusesPath, json_encode([
            'Forms' => false,
            'Announcement' => false,
        ], JSON_THROW_ON_ERROR));

        $staleWorker = new ModuleStateRepository;
        expect($staleWorker->isEnabled('Forms'))->toBeFalse();

        (new ModuleStateRepository)->setEnabled('Announcement', true);
        $staleWorker->setEnabled('Forms', true);

        expect(json_decode(File::get($statusesPath), true, 512, JSON_THROW_ON_ERROR))
            ->toMatchArray([
                'Forms' => true,
                'Announcement' => true,
            ]);
    } finally {
        File::delete($statusesPath);
        File::delete($statusesPath.'.lock');
    }
});

it('does not clear restart markers until the rollout is explicitly acknowledged', function (): void {
    $states = new ModuleStateRepository;
    $manifest = moduleStateManifest('Announcement');

    $states->sync([$manifest], 'disabled');
    DB::table('module_installations')
        ->where('module_name', 'Announcement')
        ->update(['restart_required' => true]);

    expect(Artisan::call('modules:sync-statuses', ['--mode' => 'preserve']))->toBe(0)
        ->and((bool) DB::table('module_installations')->where('module_name', 'Announcement')->value('restart_required'))->toBeTrue();

    expect(Artisan::call('modules:sync-statuses', [
        '--mode' => 'preserve',
        '--acknowledge-restart' => true,
    ]))->toBe(0)
        ->and((bool) DB::table('module_installations')->where('module_name', 'Announcement')->value('restart_required'))->toBeFalse();
});

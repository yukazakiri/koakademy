<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Jobs\CleanSystemLogs;
use App\Models\GeneralSetting;
use App\Models\LogCleanupRun;
use App\Models\User;
use App\Services\LogRetentionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

/** @return array<string, array<string, mixed>> */
function logRetentionPolicies(array $overrides = []): array
{
    $policies = [
        'pulse' => ['enabled' => false, 'retention_days' => 7, 'frequency' => 'daily', 'time' => '02:00'],
        'telescope' => ['enabled' => false, 'retention_days' => 1, 'frequency' => 'daily', 'time' => '00:00'],
        'mail' => ['enabled' => false, 'retention_days' => 30, 'frequency' => 'daily', 'time' => '02:00'],
        'activity' => ['enabled' => false, 'retention_days' => 365, 'frequency' => 'weekly', 'time' => '02:00'],
    ];

    foreach ($overrides as $source => $policy) {
        $policies[$source] = array_replace($policies[$source], $policy);
    }

    return $policies;
}

function logRetentionAdministrator(array $permissions = ['View:SystemManagementLogRetention', 'Update:SystemManagementLogRetention']): User
{
    $user = User::factory()->create(['role' => UserRole::Admin]);

    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        $user->givePermissionTo($permission);
    }

    return $user;
}

function runLogCleanup(LogRetentionService $retention, string $source, string $mode): LogCleanupRun
{
    $run = $retention->queue($source, $mode);
    (new CleanSystemLogs($run->id, $source))->handle($retention);

    return $run->refresh();
}

it('renders the logs and retention page for an authorized administrator', function (): void {
    $user = logRetentionAdministrator();

    actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/system-management/log-retention'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/system-management/log-retention', false)
            ->where('access.sections.log_retention.can_update', true)
            ->has('log_retention.sources', 4)
            ->has('log_retention.timezone')
            ->has('log_retention.recent_runs')
            ->where('log_retention.history_available', true));
});

it('denies the logs and retention page without its permissions', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/system-management/log-retention'))
        ->assertForbidden();
});

it('saves retention schedules and reports the next run for enabled sources', function (): void {
    $user = logRetentionAdministrator();

    actingAs($user)
        ->put(portalUrlForAdministrators('/administrators/system-management/log-retention'), [
            'policies' => logRetentionPolicies([
                'pulse' => ['enabled' => true, 'retention_days' => 3, 'frequency' => 'weekly', 'time' => '04:30'],
            ]),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(GeneralSetting::query()->first()?->more_configs['log_retention']['pulse'])
        ->toMatchArray(['enabled' => true, 'retention_days' => 3, 'frequency' => 'weekly', 'time' => '04:30'])
        ->and(app(LogRetentionService::class)->overview()['sources'][0]['next_run_at'])->not->toBeNull();
});

it('rejects an out of range retention period', function (): void {
    $user = logRetentionAdministrator();

    actingAs($user)
        ->put(portalUrlForAdministrators('/administrators/system-management/log-retention'), [
            'policies' => logRetentionPolicies(['mail' => ['retention_days' => 0]]),
        ])
        ->assertSessionHasErrors('policies.mail.retention_days');
});

it('refuses to clean logs without the update permission', function (): void {
    $user = logRetentionAdministrator(['View:SystemManagementLogRetention']);

    actingAs($user)
        ->post(portalUrlForAdministrators('/administrators/system-management/log-retention/clean'), [
            'source' => 'telescope',
            'mode' => 'purge',
            'confirmation' => 'PURGE',
        ])
        ->assertForbidden();
});

it('requires the typed confirmation that matches the cleanup mode', function (string $mode, string $confirmation): void {
    $user = logRetentionAdministrator();

    actingAs($user)
        ->post(portalUrlForAdministrators('/administrators/system-management/log-retention/clean'), [
            'source' => 'telescope',
            'mode' => $mode,
            'confirmation' => $confirmation,
        ])
        ->assertSessionHasErrors('confirmation');

    expect(LogCleanupRun::query()->count())->toBe(0);
})->with([
    'purge confirmed with the prune word' => ['purge', 'PRUNE'],
    'prune confirmed with the purge word' => ['prune', 'PURGE'],
    'unconfirmed' => ['purge', ''],
]);

it('queues a confirmed cleanup and deletes every eligible record', function (): void {
    $user = logRetentionAdministrator();
    app(LogRetentionService::class)->save(logRetentionPolicies(['telescope' => ['retention_days' => 7]]), $user);

    DB::table('telescope_entries')->insert([
        ['sequence' => 1, 'uuid' => 'old-entry', 'batch_id' => 'batch', 'type' => 'query', 'content' => '{}', 'created_at' => now()->subDays(10)],
        ['sequence' => 2, 'uuid' => 'kept-entry', 'batch_id' => 'batch', 'type' => 'query', 'content' => '{}', 'created_at' => now()],
    ]);

    actingAs($user)
        ->post(portalUrlForAdministrators('/administrators/system-management/log-retention/clean'), [
            'source' => 'telescope',
            'mode' => 'purge',
            'confirmation' => 'PURGE',
        ])
        ->assertRedirect();

    $run = LogCleanupRun::query()->latest('id')->firstOrFail();
    expect($run->mode)->toBe('purge')
        ->and($run->scheduled)->toBeFalse()
        ->and($run->requested_by)->toBe($user->id);

    (new CleanSystemLogs($run->id, 'telescope'))->handle(app(LogRetentionService::class));

    expect(DB::table('telescope_entries')->pluck('uuid')->all())->toBe([]);
});

it('prunes only records older than the saved retention period', function (): void {
    $user = logRetentionAdministrator();
    app(LogRetentionService::class)->save(logRetentionPolicies(['telescope' => ['retention_days' => 7]]), $user);

    DB::table('telescope_entries')->insert([
        ['sequence' => 1, 'uuid' => 'old-entry', 'batch_id' => 'batch', 'type' => 'query', 'content' => '{}', 'created_at' => now()->subDays(10)],
        ['sequence' => 2, 'uuid' => 'kept-entry', 'batch_id' => 'batch', 'type' => 'query', 'content' => '{}', 'created_at' => now()],
    ]);
    DB::table('pulse_values')->insert(['timestamp' => now()->subDays(30)->getTimestamp(), 'type' => 'request', 'key' => 'key', 'key_hash' => 'hash', 'value' => '1']);

    $retention = app(LogRetentionService::class);
    $run = runLogCleanup($retention, 'telescope', 'prune');
    $pulse = runLogCleanup($retention, 'pulse', 'prune');

    expect($run->status)->toBe('completed')
        ->and($run->deleted_records)->toBe(1)
        ->and(DB::table('telescope_entries')->pluck('uuid')->all())->toBe(['kept-entry'])
        ->and($pulse->deleted_records)->toBe(1)
        ->and(DB::table('pulse_values')->count())->toBe(0);
});

it('keeps unsuppressed recipients when purging mail logs', function (): void {
    $user = logRetentionAdministrator();
    app(LogRetentionService::class)->save(logRetentionPolicies(), $user);

    DB::table('mails')->insert([
        ['uuid' => 'deletable', 'mailer' => 'smtp', 'created_at' => now()->subDays(60)],
        ['uuid' => 'suppressed', 'mailer' => 'smtp', 'created_at' => now()->subDays(60)],
    ]);
    DB::table('mail_events')->insert(['mail_id' => 2, 'type' => 'hard_bounced', 'created_at' => now()->subDays(60)]);

    $run = runLogCleanup(app(LogRetentionService::class), 'mail', 'purge');

    expect($run->status)->toBe('completed')
        ->and($run->deleted_records)->toBe(1)
        ->and(DB::table('mails')->pluck('uuid')->all())->toBe(['suppressed'])
        ->and(DB::table('mail_events')->count())->toBe(1);
});

it('deletes stored attachment files with their mail log', function (): void {
    Storage::fake('local');
    $user = logRetentionAdministrator();
    app(LogRetentionService::class)->save(logRetentionPolicies(), $user);

    DB::table('mails')->insert(['uuid' => 'with-attachment', 'mailer' => 'smtp', 'created_at' => now()->subDays(60)]);
    DB::table('mail_attachments')->insert([
        'mail_id' => 1, 'disk' => 'local', 'uuid' => 'attachment', 'filename' => 'invoice.pdf',
        'mime' => 'application/pdf', 'size' => 9, 'inline' => false,
    ]);

    $root = mb_rtrim((string) config('mails.logging.attachments.root'), '/');
    Storage::disk('local')->put($root.'/1/invoice.pdf', 'pdf-bytes');

    runLogCleanup(app(LogRetentionService::class), 'mail', 'purge');

    expect(Storage::disk('local')->exists($root.'/1/invoice.pdf'))->toBeFalse()
        ->and(DB::table('mail_attachments')->count())->toBe(0);
});

it('protects its own cleanup audit trail from activity log purges', function (): void {
    $user = logRetentionAdministrator();
    activity('default')->causedBy($user)->log('Enrolled a student');
    activity('system-log-maintenance')->causedBy($user)->log('Purged telescope logs');

    $run = runLogCleanup(app(LogRetentionService::class), 'activity', 'purge');

    expect($run->status)->toBe('completed')
        ->and(DB::table((string) config('activitylog.database.table', 'activity_log'))->pluck('description')->all())
        ->toContain('Purged telescope logs')
        ->not->toContain('Enrolled a student');
});

it('queues each enabled schedule once per due minute', function (): void {
    $user = logRetentionAdministrator();
    app(LogRetentionService::class)->save(logRetentionPolicies([
        'telescope' => ['enabled' => true, 'time' => now()->format('H:i')],
    ]), $user);

    $retention = app(LogRetentionService::class);

    expect($retention->queueDueSchedules())->toBe(1)
        ->and($retention->queueDueSchedules())->toBe(0)
        ->and(LogCleanupRun::query()->where('scheduled', true)->count())->toBe(1);
});

it('does not queue disabled schedules', function (): void {
    $user = logRetentionAdministrator();
    app(LogRetentionService::class)->save(logRetentionPolicies([
        'telescope' => ['enabled' => false, 'time' => now()->format('H:i')],
    ]), $user);

    expect(app(LogRetentionService::class)->queueDueSchedules())->toBe(0);
});

it('reports a source whose storage is not available without failing the page', function (): void {
    config()->set('telescope.driver', 'file');

    $byKey = collect(app(LogRetentionService::class)->overview()['sources'])->keyBy('key');

    expect($byKey['telescope']['available'])->toBeFalse()
        ->and($byKey['telescope']['records'])->toBeNull()
        ->and($byKey['telescope']['error'])->toBeNull()
        ->and($byKey['pulse']['available'])->toBeTrue();
});

it('refuses to queue a cleanup for unavailable storage', function (): void {
    $user = logRetentionAdministrator();
    config()->set('telescope.driver', 'file');

    actingAs($user)
        ->post(portalUrlForAdministrators('/administrators/system-management/log-retention/clean'), [
            'source' => 'telescope',
            'mode' => 'purge',
            'confirmation' => 'PURGE',
        ])
        ->assertSessionHasErrors('source');

    expect(LogCleanupRun::query()->count())->toBe(0);
});

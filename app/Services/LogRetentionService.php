<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\CleanSystemLogs;
use App\Models\GeneralSetting;
use App\Models\LogCleanupRun;
use App\Models\User;
use Backstage\Mails\Laravel\Enums\EventType;
use Carbon\CarbonImmutable;
use Cron\CronExpression;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class LogRetentionService
{
    public const array SOURCES = ['pulse', 'telescope', 'mail', 'activity'];

    private const string CONFIG_KEY = 'log_retention';

    /** @return array<string, array{enabled: bool, retention_days: int, frequency: string, time: string}> */
    public function policies(): array
    {
        $stored = GeneralSetting::query()->first()?->more_configs[self::CONFIG_KEY] ?? [];
        $defaults = [
            'pulse' => ['enabled' => false, 'retention_days' => 7, 'frequency' => 'daily', 'time' => '02:00'],
            // Preserve the existing daily production Telescope prune until explicitly changed.
            'telescope' => ['enabled' => app()->environment('production'), 'retention_days' => 1, 'frequency' => 'daily', 'time' => '00:00'],
            'mail' => ['enabled' => false, 'retention_days' => (int) config('mails.database.pruning.after', 30), 'frequency' => 'daily', 'time' => '02:00'],
            'activity' => ['enabled' => false, 'retention_days' => (int) config('activitylog.delete_records_older_than_days', 365), 'frequency' => 'weekly', 'time' => '02:00'],
        ];

        foreach (self::SOURCES as $source) {
            $defaults[$source] = array_replace($defaults[$source], is_array($stored[$source] ?? null) ? $stored[$source] : []);
        }

        return $defaults;
    }

    /** @param array<string, array<string, mixed>> $policies */
    public function save(array $policies, User $actor): void
    {
        DB::transaction(function () use ($policies): void {
            $settings = GeneralSetting::query()->lockForUpdate()->first()
                ?? GeneralSetting::firstOrCreateGlobal(['site_name' => (string) config('app.name')]);
            $configs = $settings->more_configs ?? [];
            $configs[self::CONFIG_KEY] = $policies;
            $settings->update(['more_configs' => $configs]);
        });

        activity('system-log-maintenance')->causedBy($actor)
            ->withProperties(['policies' => $policies])->log('Updated log retention schedules');
    }

    /** @return array<string, mixed> */
    public function overview(): array
    {
        $policies = $this->policies();
        $hasHistory = DB::connection()->getSchemaBuilder()->hasTable('log_cleanup_runs');
        $sources = [];

        foreach (self::SOURCES as $source) {
            $state = [
                'key' => $source,
                'collecting' => (bool) config(match ($source) {
                    'pulse' => 'pulse.enabled', 'telescope' => 'telescope.enabled',
                    'mail' => 'mails.logging.enabled', 'activity' => 'activitylog.enabled',
                }),
                'available' => false,
                'error' => null,
                'records' => null,
                'eligible_records' => null,
                'protected_records' => null,
                'policy' => $policies[$source],
                'next_run_at' => $policies[$source]['enabled']
                    ? $this->cron($policies[$source])->getNextRunDate('now', 0, false, (string) config('app.timezone'))->format(DATE_ATOM)
                    : null,
                'active_run' => $hasHistory ? LogCleanupRun::query()->where('source', $source)->whereIn('status', ['queued', 'running'])->latest('id')->first()?->toArray() : null,
                'last_run' => $hasHistory ? LogCleanupRun::query()->where('source', $source)->latest('id')->first()?->toArray() : null,
            ];

            try {
                $state['available'] = $this->available($source);
                if ($state['available']) {
                    $cutoff = CarbonImmutable::now()->subDays($policies[$source]['retention_days']);
                    $tables = $this->tables($source);
                    $state['records'] = array_sum(array_map(fn (array $table): int => $this->connection($source)->table($table['name'])->count(), $tables));
                    $state['eligible_records'] = array_sum(array_map(fn (array $table): int => $this->eligibleQuery($source, $table, $cutoff)->count(), $tables));
                    $state['protected_records'] = $source === 'mail'
                        ? $state['records'] - $this->eligibleQuery($source, $tables[0])->count()
                        : 0;
                }
            } catch (Throwable $exception) {
                report($exception);
                $state['error'] = 'Storage could not be read. Check the configured connection and retry.';
                $state['available'] = false;
            }

            $sources[] = $state;
        }

        return [
            'timezone' => config('app.timezone'),
            'history_available' => $hasHistory,
            'sources' => $sources,
            'recent_runs' => $hasHistory ? LogCleanupRun::query()->latest('id')->limit(12)->get()->toArray() : [],
        ];
    }

    public function queue(string $source, string $mode, ?User $actor = null, bool $scheduled = false): LogCleanupRun
    {
        if (! in_array($source, self::SOURCES, true) || ! in_array($mode, ['prune', 'purge'], true)) {
            throw ValidationException::withMessages(['source' => 'Select a supported log source and cleanup mode.']);
        }

        return Cache::lock('system-log-cleanup:enqueue:'.$source, 15)->block(5, function () use ($source, $mode, $actor, $scheduled): LogCleanupRun {
            if (! $this->available($source) || ! DB::connection()->getSchemaBuilder()->hasTable('log_cleanup_runs')) {
                throw ValidationException::withMessages(['source' => 'Log storage is unavailable. Run migrations and verify its connection first.']);
            }

            if (LogCleanupRun::query()->where('source', $source)->whereIn('status', ['queued', 'running'])->exists()) {
                throw ValidationException::withMessages(['source' => 'Cleanup already queued or running for this source.']);
            }

            $now = CarbonImmutable::now();
            $upperIds = [];
            foreach ($this->tables($source) as $table) {
                $upperIds[$table['name']] = $this->connection($source)->table($table['name'])->max($table['id']) ?? 0;
            }

            $run = LogCleanupRun::query()->create([
                'source' => $source, 'mode' => $mode, 'requested_by' => $actor?->id,
                'scheduled' => $scheduled,
                'cutoff_at' => $mode === 'purge' ? $now : $now->subDays($this->policies()[$source]['retention_days']),
                'upper_ids' => $upperIds,
            ]);

            try {
                if ($actor instanceof User) {
                    activity('system-log-maintenance')->causedBy($actor)
                        ->withProperties(['run_id' => $run->id, 'source' => $source, 'mode' => $mode])
                        ->log('Requested log cleanup');
                }
                CleanSystemLogs::dispatch($run->id, $source);
            } catch (Throwable $exception) {
                $run->update(['status' => 'failed', 'finished_at' => now()]);
                throw $exception;
            }

            return $run;
        });
    }

    /** Process a finite batch. Returns true when no eligible rows remain. */
    public function processBatch(LogCleanupRun $run): bool
    {
        $source = $run->source;
        if (! $this->available($source)) {
            throw new RuntimeException('Log storage is unavailable.');
        }

        $deleted = 0;
        foreach ($this->tables($source) as $table) {
            $query = $this->eligibleQuery($source, $table, $run->mode === 'purge' ? null : $run->cutoff_at)
                ->where($table['id'], '<=', $run->upper_ids[$table['name']] ?? 0);
            $ids = (clone $query)->orderBy($table['id'])->limit($source === 'mail' ? 50 : 1000)->pluck($table['id']);
            if ($ids->isEmpty()) {
                continue;
            }

            if ($source === 'mail') {
                $this->deleteMailAttachments($ids->all());
            }

            // DELETE (not TRUNCATE) preserves identity sequences and FK cascades.
            $deleted += (clone $query)->whereIn($table['id'], $ids)->delete();
        }

        if ($deleted > 0) {
            $run->increment('deleted_records', $deleted);
        }

        return $deleted === 0;
    }

    public function queueDueSchedules(): int
    {
        if (! DB::connection()->getSchemaBuilder()->hasTable('log_cleanup_runs')) {
            return 0;
        }

        $queued = 0;
        foreach ($this->policies() as $source => $policy) {
            if (! $policy['enabled'] || ! $this->cron($policy)->isDue('now', (string) config('app.timezone'))) {
                continue;
            }

            // Durable deduplication survives repeated scheduler ticks and queued jobs.
            if (LogCleanupRun::query()->where('source', $source)->where('scheduled', true)->where('created_at', '>=', now()->startOfMinute())->exists()) {
                continue;
            }

            try {
                $this->queue($source, 'prune', scheduled: true);
                $queued++;
            } catch (ValidationException) {
                // Missing storage or an active cleanup: safely skip this scheduled tick.
            }
        }

        return $queued;
    }

    /** @param array<string, mixed> $policy */
    private function cron(array $policy): CronExpression
    {
        [$hour, $minute] = explode(':', $policy['time']);

        return new CronExpression((int) $minute.' '.(int) $hour.' * * '.($policy['frequency'] === 'weekly' ? '0' : '*'));
    }

    private function available(string $source): bool
    {
        if (($source === 'pulse' && config('pulse.storage.driver') !== 'database')
            || ($source === 'telescope' && config('telescope.driver') !== 'database')) {
            return false;
        }

        $schema = $this->connection($source)->getSchemaBuilder();
        foreach ($this->tables($source) as $table) {
            if (! $schema->hasTable($table['name'])) {
                return false;
            }
        }

        // Suppression records and attachment metadata are essential to safe mail cleanup.
        if ($source === 'mail') {
            return $schema->hasTable((string) config('mails.database.tables.events', 'mail_events'))
                && $schema->hasTable((string) config('mails.database.tables.attachments', 'mail_attachments'));
        }

        return true;
    }

    private function connection(string $source): Connection
    {
        return DB::connection(match ($source) {
            'pulse' => config('pulse.storage.database.connection'),
            'telescope' => config('telescope.storage.database.connection'),
            'activity' => (new (config('activitylog.activity_model')))->getConnectionName(),
            'mail' => (new (config('mails.models.mail')))->getConnectionName(),
        });
    }

    /** @return list<array{name: string, id: string, date: string}> */
    private function tables(string $source): array
    {
        return match ($source) {
            'pulse' => [
                ['name' => 'pulse_entries', 'id' => 'id', 'date' => 'timestamp'],
                ['name' => 'pulse_values', 'id' => 'id', 'date' => 'timestamp'],
                ['name' => 'pulse_aggregates', 'id' => 'id', 'date' => 'bucket'],
            ],
            'telescope' => [['name' => 'telescope_entries', 'id' => 'sequence', 'date' => 'created_at']],
            'mail' => [['name' => (new (config('mails.models.mail')))->getTable(), 'id' => 'id', 'date' => 'created_at']],
            'activity' => [['name' => (new (config('activitylog.activity_model')))->getTable(), 'id' => 'id', 'date' => 'created_at']],
        };
    }

    /** @param array{name: string, id: string, date: string} $table */
    private function eligibleQuery(string $source, array $table, ?CarbonImmutable $cutoff = null): Builder
    {
        $query = $this->connection($source)->table($table['name']);
        if ($cutoff !== null) {
            $query->where($table['date'], '<', $source === 'pulse' ? $cutoff->getTimestamp() : $cutoff);
        }

        if ($source === 'activity') {
            $query->where(fn (Builder $query) => $query->whereNull('log_name')->orWhere('log_name', '!=', 'system-log-maintenance'));
        }

        if ($source === 'mail') {
            $events = (string) config('mails.database.tables.events', 'mail_events');
            $query->whereNotExists(function (Builder $query) use ($events, $table): void {
                $query->selectRaw('1')->from($events)
                    ->whereColumn($events.'.mail_id', $table['name'].'.id')
                    ->whereIn('type', [EventType::HARD_BOUNCED->value, EventType::COMPLAINED->value, EventType::UNSUBSCRIBED->value])
                    ->whereNull('unsuppressed_at');
            });
        }

        return $query;
    }

    /** @param list<int> $mailIds */
    private function deleteMailAttachments(array $mailIds): void
    {
        $table = (string) config('mails.database.tables.attachments', 'mail_attachments');
        $root = mb_trim((string) config('mails.logging.attachments.root', 'mails/attachments'), '/');
        if ($root === '' || str_contains($root, '..') || str_contains($root, '\\')) {
            throw new RuntimeException('Mail attachment root is unsafe for cleanup.');
        }

        foreach ($this->connection('mail')->table($table)->whereIn('mail_id', $mailIds)->cursor() as $attachment) {
            $filename = (string) $attachment->filename;
            if ($filename === '' || $filename === '.' || $filename === '..' || str_contains($filename, '/') || str_contains($filename, '\\')) {
                throw new RuntimeException('Mail attachment filename is unsafe for cleanup.');
            }
            $disk = Storage::disk($attachment->disk);
            $path = $root.'/'.$attachment->id.'/'.$filename;
            if ($disk->exists($path) && ! $disk->delete($path)) {
                throw new RuntimeException('Mail attachment could not be removed.');
            }
        }
    }
}

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Drops every table owned by the removed ojbaeza/station package,
     * which has been replaced by Laravel Horizon.
     */
    public function up(): void
    {
        $tables = [
            'station_alert_history',
            'station_alert_channels',
            'station_alert_rules',
            'station_driver_snapshots',
            'station_workflows',
            'station_queue_status',
            'station_job_events',
            'station_kafka_delayed_jobs',
            'station_checkpoints',
            'station_batches',
            'station_failed_jobs',
            'station_jobs',
            'station_metrics',
            'station_supervisors',
            'station_workers',
            'station_audit_log',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }

    /**
     * Reverse the migrations.
     *
     * Intentionally left empty: the dropped tables belonged to the
     * removed ojbaeza/station package and are not recreated.
     */
    public function down(): void
    {
        //
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class CreateActivityLogTable extends Migration
{
    public function up(): void
    {
        if (! Schema::connection(config('activitylog.database_connection'))->hasTable(config('activitylog.table_name'))) {
            Schema::connection(config('activitylog.database_connection'))->create(config('activitylog.table_name'), function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('log_name')->nullable();
                $table->text('description');
                $table->nullableMorphs('subject', 'activity_log_subject_index');
                $table->nullableMorphs('causer', 'activity_log_causer_index');
                $table->json('properties')->nullable();
                $table->timestamps();
                $table->index('log_name');
            });
        }
    }

    public function down(): void
    {
        Schema::connection(config('activitylog.database_connection'))->dropIfExists(config('activitylog.table_name'));
    }
}

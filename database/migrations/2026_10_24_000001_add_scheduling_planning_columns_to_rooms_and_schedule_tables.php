<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('rooms')) {
            Schema::table('rooms', function (Blueprint $table): void {
                if (! Schema::hasColumn('rooms', 'capacity')) {
                    $table->unsignedInteger('capacity')->default(40)->after('class_code');
                }
                if (! Schema::hasColumn('rooms', 'type')) {
                    $table->string('type', 50)->default('lecture')->after('capacity');
                }
                if (! Schema::hasColumn('rooms', 'building')) {
                    $table->string('building', 100)->nullable()->after('type');
                }
            });
        }

        if (Schema::hasTable('schedule')) {
            Schema::table('schedule', function (Blueprint $table): void {
                if (! Schema::hasColumn('schedule', 'subject_id')) {
                    $table->foreignId('subject_id')->nullable()->after('class_id')->constrained('subject')->nullOnDelete();
                }
                if (! Schema::hasColumn('schedule', 'component_type')) {
                    $table->string('component_type', 30)->default('lecture')->after('room_id');
                }
                if (! Schema::hasColumn('schedule', 'pattern_group_id')) {
                    $table->string('pattern_group_id', 64)->nullable()->after('component_type');
                }
                if (! Schema::hasColumn('schedule', 'status')) {
                    $table->string('status', 20)->default('published')->after('pattern_group_id');
                }

                $table->index(['day_of_week', 'room_id', 'start_time', 'end_time'], 'idx_schedule_day_room_time');
                $table->index(['class_id', 'day_of_week'], 'idx_schedule_class_day');
                $table->index(['pattern_group_id'], 'idx_schedule_pattern_group');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('schedule')) {
            Schema::table('schedule', function (Blueprint $table): void {
                $table->dropIndex('idx_schedule_day_room_time');
                $table->dropIndex('idx_schedule_class_day');
                $table->dropIndex('idx_schedule_pattern_group');

                if (Schema::hasColumn('schedule', 'subject_id')) {
                    $table->dropConstrainedForeignId('subject_id');
                }

                $columnsToDrop = array_values(array_filter(
                    ['component_type', 'pattern_group_id', 'status'],
                    fn (string $column): bool => Schema::hasColumn('schedule', $column)
                ));

                if ($columnsToDrop !== []) {
                    $table->dropColumn($columnsToDrop);
                }
            });
        }

        if (Schema::hasTable('rooms')) {
            Schema::table('rooms', function (Blueprint $table): void {
                $columnsToDrop = array_values(array_filter(
                    ['capacity', 'type', 'building'],
                    fn (string $column): bool => Schema::hasColumn('rooms', $column)
                ));

                if ($columnsToDrop !== []) {
                    $table->dropColumn($columnsToDrop);
                }
            });
        }
    }
};

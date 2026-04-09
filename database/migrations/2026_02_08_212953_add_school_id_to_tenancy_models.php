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
        // Add school_id to classes table for multi-tenancy
        Schema::table('classes', function (Blueprint $table): void {
            $table->foreignId('school_id')
                ->nullable()
                ->after('id')
                ->constrained('schools')
                ->nullOnDelete();

            $table->index('school_id');
        });

        // Add school_id to announcements table
        if (Schema::hasTable('announcements') && ! Schema::hasColumn('announcements', 'school_id')) {
            Schema::table('announcements', function (Blueprint $table): void {
                $table->foreignId('school_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('schools')
                    ->nullOnDelete();

                $table->index('school_id');
            });
        }

        // Add school_id to rooms table
        if (Schema::hasTable('rooms') && ! Schema::hasColumn('rooms', 'school_id')) {
            Schema::table('rooms', function (Blueprint $table): void {
                $table->foreignId('school_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('schools')
                    ->nullOnDelete();

                $table->index('school_id');
            });
        }

        // Add school_id to subject table
        if (Schema::hasTable('subject') && ! Schema::hasColumn('subject', 'school_id')) {
            Schema::table('subject', function (Blueprint $table): void {
                $table->foreignId('school_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('schools')
                    ->nullOnDelete();

                $table->index('school_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table): void {
            $table->dropForeign(['school_id']);
            $table->dropColumn('school_id');
        });

        if (Schema::hasColumn('announcements', 'school_id')) {
            Schema::table('announcements', function (Blueprint $table): void {
                $table->dropForeign(['school_id']);
                $table->dropColumn('school_id');
            });
        }

        if (Schema::hasColumn('rooms', 'school_id')) {
            Schema::table('rooms', function (Blueprint $table): void {
                $table->dropForeign(['school_id']);
                $table->dropColumn('school_id');
            });
        }

        if (Schema::hasColumn('subject', 'school_id')) {
            Schema::table('subject', function (Blueprint $table): void {
                $table->dropForeign(['school_id']);
                $table->dropColumn('school_id');
            });
        }
    }
};

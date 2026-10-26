<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $hasOldIndex = collect(DB::select("PRAGMA index_list('faculty')"))
                ->contains(fn (object $index): bool => ($index->name ?? '') === 'faculty_email_unique');

            if ($hasOldIndex) {
                DB::statement('DROP INDEX "faculty_email_unique"');
            }

            $hasNewIndex = collect(DB::select("PRAGMA index_list('faculty')"))
                ->contains(fn (object $index): bool => ($index->name ?? '') === 'faculty_email_school_id_unique');

            if (! $hasNewIndex) {
                DB::statement('CREATE UNIQUE INDEX "faculty_email_school_id_unique" ON "faculty" ("email", "school_id")');
            }

            return;
        }

        Schema::table('faculty', function (Blueprint $table): void {
            $table->dropUnique('faculty_email_unique');
            $table->unique(['email', 'school_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS "faculty_email_school_id_unique"');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS "faculty_email_unique" ON "faculty" ("email")');

            return;
        }

        Schema::table('faculty', function (Blueprint $table): void {
            $table->dropUnique(['email', 'school_id']);
            $table->unique('email');
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('id_sequences', function (Blueprint $table): void {
            $table->dropUnique(['key']);
            $table->string('scope')->default('installation');
            $table->unique(['scope', 'key']);
            $table->string('assignment_mode')->default('hybrid');
            $table->string('character_set')->default('numeric');
            $table->string('display_label')->default('Student ID');
            $table->string('counter_scope')->default('shared');
            $table->boolean('legacy_format')->default(false);
        });
        DB::table('id_sequences')->update(['legacy_format' => true]);
        foreach (DB::table('id_sequences')->where('key', 'student')->get() as $sequence) {
            $prefixes = json_decode($sequence->type_prefixes ?? '[]', true, flags: JSON_THROW_ON_ERROR);
            foreach (['college' => '2', 'tesda' => '2', 'dhrt' => '2', 'shs' => '3'] as $type => $prefix) {
                // The former resolver fell back on missing *and* empty entries.
                if (! isset($prefixes[$type]) || $prefixes[$type] === '') {
                    $prefixes[$type] = $prefix;
                }
            }
            DB::table('id_sequences')->where('id', $sequence->id)->update([
                'type_prefixes' => json_encode($prefixes, JSON_THROW_ON_ERROR),
                'min_length' => $sequence->min_length ?? 4,
                'max_length' => $sequence->max_length ?? 12,
                'exact_length' => $sequence->exact_length ?? 6,
            ]);
        }
        // Freeze formerly lazy defaults now, before an administrator can change a
        // preset. No future defaults change may reset an upgraded installation.
        if (DB::table('student_configurations')->where('scope', 'installation')->exists()) {
            foreach (['student' => 200000, 'staff' => 800000] as $key => $start) {
                if (DB::table('id_sequences')->where('scope', 'installation')->where('key', $key)->exists()) {
                    continue;
                }
                $last = $key === 'student'
                    ? DB::table('students')->where('student_type', '!=', 'shs')->whereNotNull('student_id')->orderByDesc('created_at')->orderByDesc('id')->value('student_id')
                    : null;
                if ($key === 'staff') {
                    DB::table('faculty')->whereNotNull('faculty_id_number')->orderBy('id')->chunkById(1000, function ($faculty) use (&$last): void {
                        foreach ($faculty as $member) {
                            if (ctype_digit((string) $member->faculty_id_number)) {
                                $last = max((int) $last, (int) $member->faculty_id_number);
                            }
                        }
                    });
                }
                DB::table('id_sequences')->insert([
                    'scope' => 'installation', 'key' => $key, 'label' => $key === 'student' ? 'Student IDs' : 'Staff IDs',
                    'start_number' => $start, 'next_number' => max($start, (int) $last + 1), 'increment_by' => 1, 'padding' => 6,
                    'prefix_mode' => 'none', 'enforce_prefix' => false, 'enforce_length' => false,
                    'exact_length' => 6, 'min_length' => 4, 'max_length' => 12, 'legacy_format' => true,
                    'type_prefixes' => json_encode(['college' => '2', 'tesda' => '2', 'dhrt' => '2', 'shs' => '3'], JSON_THROW_ON_ERROR),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        // Copy the actual saved counters without repairing, resetting or renumbering.
        $sequences = DB::table('id_sequences')->where('scope', 'installation')->where('key', 'student')->get();
        DB::table('schools')->orderBy('id')->chunkById(100, function ($schools) use ($sequences): void {
            foreach ($schools as $school) {
                foreach ($sequences as $sequence) {
                    $copy = (array) $sequence;
                    unset($copy['id']);
                    $copy['scope'] = 'school:'.$school->id;
                    DB::table('id_sequences')->insert($copy);
                }
            }
        });

        Schema::table('students', function (Blueprint $table): void {
            $table->string('student_id', 64)->nullable()->change();
            // A shadow key allows preserving existing duplicate legacy rows while
            // enforcing uniqueness on all newly assigned identifiers, including races.
            $table->string('identifier_key', 100)->nullable()->unique();
        });
        DB::table('students')->whereNotNull('student_id')->orderBy('id')->chunkById(500, function ($students): void {
            foreach ($students as $student) {
                $scope = $student->school_id === null ? 'installation' : 'school:'.$student->school_id;
                $key = $scope.':'.$student->student_id;
                if (! DB::table('students')->where('identifier_key', $key)->exists()) {
                    DB::table('students')->where('id', $student->id)->update(['identifier_key' => $key]);
                }
            }
        });

        // Legacy code assigned primary keys with MAX(id)+1. Bring PostgreSQL's
        // independent identity sequence forward before restoring native allocation.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('students', 'id'), COALESCE((SELECT MAX(id) FROM students), 0) + 1, false)");
        }
    }

    public function down(): void
    {
        // Converting arbitrary identifiers back to integers or merging independently
        // advanced school counters is lossy. Use a forward migration in production.
        throw new RuntimeException('Student identifier formatting cannot be rolled back without losing identifiers or school counters. Restore a pre-upgrade backup instead.');
    }
};

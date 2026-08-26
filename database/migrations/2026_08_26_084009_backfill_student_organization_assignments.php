<?php

declare(strict_types=1);

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('students') || ! Schema::hasTable('users') || ! Schema::hasTable('schools') || ! Schema::hasTable('organization_user')) {
            return;
        }

        $assignments = DB::table('students')
            ->join('users', 'users.id', '=', 'students.user_id')
            ->join('schools', 'schools.id', '=', 'students.school_id')
            ->whereNull('users.school_id')
            ->whereNull('students.deleted_at')
            ->whereNull('schools.deleted_at')
            ->where('schools.is_active', true)
            ->whereNotNull('students.school_id')
            ->whereIn('users.role', [
                UserRole::Student->value,
                UserRole::GraduateStudent->value,
                UserRole::ShsStudent->value,
            ])
            ->select(['users.id as user_id', 'users.role', 'students.id as student_record_id', 'students.school_id'])
            ->get()
            ->groupBy('user_id')
            ->filter(fn ($rows): bool => $rows->pluck('school_id')->unique()->count() === 1)
            ->map(function ($rows) {
                $assignment = $rows->first();
                $assignment->student_record_id = $rows->pluck('student_record_id')->unique()->count() === 1
                    ? $assignment->student_record_id
                    : null;

                return $assignment;
            });

        foreach ($assignments as $assignment) {
            DB::transaction(function () use ($assignment): void {
                $attributes = ['school_id' => $assignment->school_id];

                if ($assignment->student_record_id !== null) {
                    $attributes['record_id'] = $assignment->student_record_id;
                }

                $updated = DB::table('users')
                    ->where('id', $assignment->user_id)
                    ->whereNull('school_id')
                    ->update($attributes);

                if ($updated !== 1) {
                    return;
                }

                DB::table('organization_user')
                    ->where('user_id', $assignment->user_id)
                    ->where('school_id', '!=', $assignment->school_id)
                    ->update(['is_primary' => false]);

                $membership = DB::table('organization_user')
                    ->where('user_id', $assignment->user_id)
                    ->where('school_id', $assignment->school_id);

                if ($membership->exists()) {
                    $membership->update([
                        'role' => $assignment->role,
                        'is_primary' => true,
                        'is_active' => true,
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table('organization_user')->insert([
                        'user_id' => $assignment->user_id,
                        'school_id' => $assignment->school_id,
                        'role' => $assignment->role,
                        'is_primary' => true,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This data repair cannot be reversed without losing legitimate assignments.
    }
};

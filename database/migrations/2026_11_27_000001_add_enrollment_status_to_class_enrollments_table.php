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
        if (! Schema::hasTable('class_enrollments')) {
            return;
        }

        $hasEnrollmentStatus = Schema::hasColumn('class_enrollments', 'enrollment_status');
        $hasStatusReason = Schema::hasColumn('class_enrollments', 'status_reason');

        if (! $hasEnrollmentStatus || ! $hasStatusReason) {
            Schema::table('class_enrollments', function (Blueprint $table) use ($hasEnrollmentStatus, $hasStatusReason): void {
                if (! $hasEnrollmentStatus) {
                    $table->string('enrollment_status', 32)
                        ->default('enrolled')
                        ->index('class_enrollments_enrollment_status_index');
                }

                if (! $hasStatusReason) {
                    $table->text('status_reason')->nullable();
                }
            });
        }

        if (Schema::hasColumn('class_enrollments', 'enrollment_status')) {
            DB::table('class_enrollments')
                ->where('status', false)
                ->update(['enrollment_status' => 'dropped']);

            DB::table('class_enrollments')
                ->where(function ($query): void {
                    $query->where('status', true)
                        ->orWhereNull('status');
                })
                ->update(['enrollment_status' => 'enrolled']);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('class_enrollments')) {
            return;
        }

        $hasEnrollmentStatus = Schema::hasColumn('class_enrollments', 'enrollment_status');
        $hasStatusReason = Schema::hasColumn('class_enrollments', 'status_reason');

        if (! $hasEnrollmentStatus && ! $hasStatusReason) {
            return;
        }

        Schema::table('class_enrollments', function (Blueprint $table) use ($hasEnrollmentStatus, $hasStatusReason): void {
            if ($hasEnrollmentStatus) {
                $table->dropIndex('class_enrollments_enrollment_status_index');
                $table->dropColumn('enrollment_status');
            }

            if ($hasStatusReason) {
                $table->dropColumn('status_reason');
            }
        });
    }
};

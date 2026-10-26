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
        // 1. student_tuition.enrollment_id -> RESTRICT
        if (Schema::hasTable('student_tuition')) {
            Schema::table('student_tuition', function (Blueprint $table): void {
                $table->dropForeign(['enrollment_id']);
            });
            Schema::table('student_tuition', function (Blueprint $table): void {
                $table->foreign('enrollment_id')
                    ->references('id')
                    ->on('student_enrollment')
                    ->restrictOnDelete();
            });
        }

        // 2. admin_transactions.admin_id & transaction_id -> RESTRICT
        if (Schema::hasTable('admin_transactions')) {
            Schema::table('admin_transactions', function (Blueprint $table): void {
                $table->dropForeign(['admin_id']);
                $table->dropForeign(['transaction_id']);
            });
            Schema::table('admin_transactions', function (Blueprint $table): void {
                $table->foreign('admin_id')
                    ->references('id')
                    ->on('users')
                    ->restrictOnDelete();
                $table->foreign('transaction_id')
                    ->references('id')
                    ->on('transactions')
                    ->restrictOnDelete();
            });
        }

        // 3. student_transactions.transaction_id -> RESTRICT
        if (Schema::hasTable('student_transactions')) {
            Schema::table('student_transactions', function (Blueprint $table): void {
                $table->dropForeign(['transaction_id']);
            });
            Schema::table('student_transactions', function (Blueprint $table): void {
                $table->foreign('transaction_id')
                    ->references('id')
                    ->on('transactions')
                    ->restrictOnDelete();
            });
        }

        // 4. statement_of_account_issuances.student_id -> RESTRICT
        if (Schema::hasTable('statement_of_account_issuances')) {
            Schema::table('statement_of_account_issuances', function (Blueprint $table): void {
                $table->dropForeign(['student_id']);
            });
            Schema::table('statement_of_account_issuances', function (Blueprint $table): void {
                $table->foreign('student_id')
                    ->references('id')
                    ->on('students')
                    ->restrictOnDelete();
            });
        }

        // 5. payment_allocations.transaction_id & student_id -> RESTRICT
        if (Schema::hasTable('payment_allocations')) {
            Schema::table('payment_allocations', function (Blueprint $table): void {
                $table->dropForeign(['transaction_id']);
                $table->dropForeign(['student_id']);
            });
            Schema::table('payment_allocations', function (Blueprint $table): void {
                $table->foreign('transaction_id')
                    ->references('id')
                    ->on('transactions')
                    ->restrictOnDelete();
                $table->foreign('student_id')
                    ->references('id')
                    ->on('students')
                    ->restrictOnDelete();
            });
        }

        // 6. additional_fees.enrollment_id -> RESTRICT
        if (Schema::hasTable('additional_fees')) {
            Schema::table('additional_fees', function (Blueprint $table): void {
                $table->dropForeign(['enrollment_id']);
            });
            Schema::table('additional_fees', function (Blueprint $table): void {
                $table->foreign('enrollment_id')
                    ->references('id')
                    ->on('student_enrollment')
                    ->restrictOnDelete();
            });
        }

        // 7. assessment_revisions.student_tuition_id & student_enrollment_id -> RESTRICT
        if (Schema::hasTable('assessment_revisions')) {
            Schema::table('assessment_revisions', function (Blueprint $table): void {
                $table->dropForeign(['student_tuition_id']);
                $table->dropForeign(['student_enrollment_id']);
            });
            Schema::table('assessment_revisions', function (Blueprint $table): void {
                $table->foreign('student_tuition_id')
                    ->references('id')
                    ->on('student_tuition')
                    ->restrictOnDelete();
                $table->foreign('student_enrollment_id')
                    ->references('id')
                    ->on('student_enrollment')
                    ->restrictOnDelete();
            });
        }

        // 8. student_tuition_update_requests.submitted_by_user_id & student_id -> RESTRICT
        if (Schema::hasTable('student_tuition_update_requests')) {
            Schema::table('student_tuition_update_requests', function (Blueprint $table): void {
                $table->dropForeign(['submitted_by_user_id']);
                $table->dropForeign(['student_id']);
            });
            Schema::table('student_tuition_update_requests', function (Blueprint $table): void {
                $table->foreign('submitted_by_user_id')
                    ->references('id')
                    ->on('users')
                    ->restrictOnDelete();
                $table->foreign('student_id')
                    ->references('id')
                    ->on('students')
                    ->restrictOnDelete();
            });
        }

        // 9. general_settings.school_id -> SET NULL
        if (Schema::hasTable('general_settings')) {
            Schema::table('general_settings', function (Blueprint $table): void {
                $table->dropForeign(['school_id']);
            });
            Schema::table('general_settings', function (Blueprint $table): void {
                $table->foreign('school_id')
                    ->references('id')
                    ->on('schools')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('general_settings')) {
            Schema::table('general_settings', function (Blueprint $table): void {
                $table->dropForeign(['school_id']);
            });
            Schema::table('general_settings', function (Blueprint $table): void {
                $table->foreign('school_id')
                    ->references('id')
                    ->on('schools')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('student_tuition_update_requests')) {
            Schema::table('student_tuition_update_requests', function (Blueprint $table): void {
                $table->dropForeign(['submitted_by_user_id']);
                $table->dropForeign(['student_id']);
            });
            Schema::table('student_tuition_update_requests', function (Blueprint $table): void {
                $table->foreign('submitted_by_user_id')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();
                $table->foreign('student_id')
                    ->references('id')
                    ->on('students')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('assessment_revisions')) {
            Schema::table('assessment_revisions', function (Blueprint $table): void {
                $table->dropForeign(['student_tuition_id']);
                $table->dropForeign(['student_enrollment_id']);
            });
            Schema::table('assessment_revisions', function (Blueprint $table): void {
                $table->foreign('student_tuition_id')
                    ->references('id')
                    ->on('student_tuition')
                    ->cascadeOnDelete();
                $table->foreign('student_enrollment_id')
                    ->references('id')
                    ->on('student_enrollment')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('additional_fees')) {
            Schema::table('additional_fees', function (Blueprint $table): void {
                $table->dropForeign(['enrollment_id']);
            });
            Schema::table('additional_fees', function (Blueprint $table): void {
                $table->foreign('enrollment_id')
                    ->references('id')
                    ->on('student_enrollment')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('payment_allocations')) {
            Schema::table('payment_allocations', function (Blueprint $table): void {
                $table->dropForeign(['transaction_id']);
                $table->dropForeign(['student_id']);
            });
            Schema::table('payment_allocations', function (Blueprint $table): void {
                $table->foreign('transaction_id')
                    ->references('id')
                    ->on('transactions')
                    ->cascadeOnDelete();
                $table->foreign('student_id')
                    ->references('id')
                    ->on('students')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('statement_of_account_issuances')) {
            Schema::table('statement_of_account_issuances', function (Blueprint $table): void {
                $table->dropForeign(['student_id']);
            });
            Schema::table('statement_of_account_issuances', function (Blueprint $table): void {
                $table->foreign('student_id')
                    ->references('id')
                    ->on('students')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('student_transactions')) {
            Schema::table('student_transactions', function (Blueprint $table): void {
                $table->dropForeign(['transaction_id']);
            });
            Schema::table('student_transactions', function (Blueprint $table): void {
                $table->foreign('transaction_id')
                    ->references('id')
                    ->on('transactions')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('admin_transactions')) {
            Schema::table('admin_transactions', function (Blueprint $table): void {
                $table->dropForeign(['admin_id']);
                $table->dropForeign(['transaction_id']);
            });
            Schema::table('admin_transactions', function (Blueprint $table): void {
                $table->foreign('admin_id')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();
                $table->foreign('transaction_id')
                    ->references('id')
                    ->on('transactions')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('student_tuition')) {
            Schema::table('student_tuition', function (Blueprint $table): void {
                $table->dropForeign(['enrollment_id']);
            });
            Schema::table('student_tuition', function (Blueprint $table): void {
                $table->foreign('enrollment_id')
                    ->references('id')
                    ->on('student_enrollment')
                    ->cascadeOnDelete();
            });
        }
    }
};

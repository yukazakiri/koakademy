<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_tuition_update_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('student_tuition_update_requests', 'amount')) {
                $table->decimal('amount', 10, 2)->nullable()->after('concern_type');
            }
        });

        if (! Schema::hasTable('student_tuition_update_request_receipts')) {
            Schema::create('student_tuition_update_request_receipts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('student_tuition_update_request_id')
                    ->constrained('student_tuition_update_requests')
                    ->cascadeOnDelete();
                $table->string('receipt_number', 255);
                $table->decimal('amount', 10, 2)->nullable();
                $table->string('image_path', 1000)->nullable();
                $table->string('file_name', 255)->nullable();
                $table->string('mime_type', 100)->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->timestamps();

                $table->index(['student_tuition_update_request_id', 'created_at'], 'tuition_update_receipts_request_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_tuition_update_request_receipts');

        Schema::table('student_tuition_update_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('student_tuition_update_requests', 'amount')) {
                $table->dropColumn('amount');
            }
        });
    }
};

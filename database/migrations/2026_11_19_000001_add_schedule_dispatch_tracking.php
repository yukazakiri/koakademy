<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_schedule_change_student_notifications', function (Blueprint $table): void {
            $table->boolean('auto_dispatch')->default(false);
            $table->timestamp('database_queued_at')->nullable();
            $table->timestamp('mail_queued_at')->nullable();
            $table->index(['auto_dispatch', 'notified_at'], 'class_schedule_auto_dispatch_pending');
        });
    }

    public function down(): void
    {
        Schema::table('class_schedule_change_student_notifications', function (Blueprint $table): void {
            $table->dropIndex('class_schedule_auto_dispatch_pending');
            $table->dropColumn(['auto_dispatch', 'database_queued_at', 'mail_queued_at']);
        });
    }
};

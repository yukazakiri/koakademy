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
        Schema::table('class_posts', function (Blueprint $table): void {
            $table->string('status')->default('backlog')->after('type');
            $table->string('priority')->default('medium')->after('status');
            $table->date('start_date')->nullable()->after('priority');
            $table->date('due_date')->nullable()->after('start_date');
            $table->unsignedTinyInteger('progress_percent')->default(0)->after('due_date');
            $table->uuid('assigned_faculty_id')->nullable()->after('progress_percent');

            $table->foreign('assigned_faculty_id')
                ->references('id')
                ->on('faculty')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('class_posts', function (Blueprint $table): void {
            $table->dropForeign(['assigned_faculty_id']);
            $table->dropColumn([
                'status',
                'priority',
                'start_date',
                'due_date',
                'progress_percent',
                'assigned_faculty_id',
            ]);
        });
    }
};

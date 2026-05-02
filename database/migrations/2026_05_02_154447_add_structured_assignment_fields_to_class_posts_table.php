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
            $table->text('instruction')->nullable()->after('content');
            $table->string('audience_mode')->default('all_students')->after('instruction');
            $table->json('assigned_student_ids')->nullable()->after('audience_mode');
            $table->json('rubric')->nullable()->after('assigned_student_ids');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('class_posts', function (Blueprint $table): void {
            $table->dropColumn([
                'instruction',
                'audience_mode',
                'assigned_student_ids',
                'rubric',
            ]);
        });
    }
};

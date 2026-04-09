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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('faculty_id_number')->nullable()->after('department_id');
            $table->string('record_id')->nullable()->after('faculty_id_number');
            $table->index(['faculty_id_number']);
            $table->index(['record_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['faculty_id_number']);
            $table->dropIndex(['record_id']);
            $table->dropColumn(['faculty_id_number', 'record_id']);
        });
    }
};

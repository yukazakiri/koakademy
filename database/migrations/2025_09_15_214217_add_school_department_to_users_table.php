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
            $table->foreignId('school_id')->nullable()->after('role')->constrained('schools')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->after('school_id')->constrained('departments')->nullOnDelete();

            // Add indexes for performance
            $table->index(['school_id', 'role']);
            $table->index(['department_id', 'role']);
            $table->index(['school_id', 'department_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['school_id', 'department_id', 'role']);
            $table->dropIndex(['department_id', 'role']);
            $table->dropIndex(['school_id', 'role']);

            $table->dropForeign(['department_id']);
            $table->dropForeign(['school_id']);
            $table->dropColumn(['department_id', 'school_id']);
        });
    }
};

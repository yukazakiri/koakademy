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
        Schema::table('schools', function (Blueprint $table): void {
            $table->string('curriculum_framework', 50)
                ->nullable()
                ->after('school_level')
                ->index();
            $table->string('curriculum_reference', 255)
                ->nullable()
                ->after('curriculum_framework');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table): void {
            $table->dropIndex(['curriculum_framework']);
            $table->dropColumn(['curriculum_framework', 'curriculum_reference']);
        });
    }
};

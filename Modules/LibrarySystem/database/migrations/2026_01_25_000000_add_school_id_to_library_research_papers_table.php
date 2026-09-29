<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_research_papers', function (Blueprint $table): void {
            $table->foreignId('school_id')->nullable()->after('id')->constrained('schools')->cascadeOnDelete();
            $table->index('school_id');
        });
    }

    public function down(): void
    {
        Schema::table('library_research_papers', function (Blueprint $table): void {
            $table->dropForeign(['school_id']);
            $table->dropIndex(['school_id']);
            $table->dropColumn('school_id');
        });
    }
};

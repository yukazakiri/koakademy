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
            $table->string('document_path')->nullable()->after('document_url');
            $table->string('cover_image_path')->nullable()->after('document_path');
        });
    }

    public function down(): void
    {
        Schema::table('library_research_papers', function (Blueprint $table): void {
            $table->dropColumn(['document_path', 'cover_image_path']);
        });
    }
};

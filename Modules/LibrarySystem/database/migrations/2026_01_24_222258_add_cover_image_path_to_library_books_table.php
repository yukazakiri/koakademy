<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_books', function (Blueprint $table): void {
            $table->string('cover_image_path')->nullable()->after('cover_image');
        });
    }

    public function down(): void
    {
        Schema::table('library_books', function (Blueprint $table): void {
            $table->dropColumn('cover_image_path');
        });
    }
};

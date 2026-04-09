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
        if (! Schema::hasTable('resources')) {
            Schema::create('resources', function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->string('name');
                $blueprint->string('type'); // 'assessment', 'certificate', etc.
                $blueprint->string('file_path');
                $blueprint->string('mime_type')->nullable();
                $blueprint->bigInteger('file_size')->nullable();
                $blueprint->morphs('resourceable'); // polymorphic relationship
                $blueprint->text('description')->nullable();
                $blueprint->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};

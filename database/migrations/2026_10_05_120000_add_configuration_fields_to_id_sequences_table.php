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
        Schema::table('id_sequences', function (Blueprint $table): void {
            $table->string('prefix_mode')->default('none');
            $table->string('prefix_value')->nullable();
            $table->boolean('enforce_prefix')->default(false);
            $table->boolean('enforce_length')->default(false);
            $table->unsignedTinyInteger('exact_length')->nullable();
            $table->unsignedTinyInteger('min_length')->nullable()->default(4);
            $table->unsignedTinyInteger('max_length')->nullable()->default(12);
            $table->json('type_prefixes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('id_sequences', function (Blueprint $table): void {
            $table->dropColumn([
                'prefix_mode',
                'prefix_value',
                'enforce_prefix',
                'enforce_length',
                'exact_length',
                'min_length',
                'max_length',
                'type_prefixes',
            ]);
        });
    }
};

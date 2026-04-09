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
        if (! Schema::hasTable('document_locations')) {
            Schema::create('document_locations', function (Blueprint $table): void {
                $table->id();
                $table->string('picture_1x1')->nullable();
                $table->string('picture_2x2')->nullable();
                $table->string('birth_certificate')->nullable();
                $table->string('transcript_records')->nullable();
                $table->string('good_moral')->nullable();
                $table->string('form_138')->nullable();
                $table->string('form_137')->nullable();
                $table->string('good_moral_cert')->nullable();
                $table->string('transfer_credentials')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_locations');
    }
};

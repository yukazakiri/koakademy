<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faculty_bulk_imports', function (Blueprint $table): void {
            $table->json('field_proposals')->nullable()->after('checksum');
        });
    }

    public function down(): void
    {
        Schema::table('faculty_bulk_imports', function (Blueprint $table): void {
            $table->dropColumn('field_proposals');
        });
    }
};

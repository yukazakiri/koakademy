<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('students', 'profile_details')) {
            Schema::table('students', function (Blueprint $table): void {
                $table->jsonb('profile_details')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('students', 'profile_details')) {
            Schema::table('students', function (Blueprint $table): void {
                $table->dropColumn('profile_details');
            });
        }
    }
};

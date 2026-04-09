<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table): void {
            if (! Schema::hasColumn('announcements', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('ends_at');
            }
            if (! Schema::hasColumn('announcements', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('starts_at');
            }
            if (! Schema::hasColumn('announcements', 'status')) {
                $table->string('status')->default('draft')->after('type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table): void {
            $table->dropColumn(['expires_at', 'published_at', 'status']);
        });
    }
};

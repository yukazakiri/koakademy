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
        if (! Schema::hasTable('announcements')) {
            return;
        }

        Schema::table('announcements', function (Blueprint $table): void {
            if (! Schema::hasColumn('announcements', 'attachments')) {
                $table->json('attachments')->nullable();
            }

            if (! Schema::hasColumn('announcements', 'class_id')) {
                $table->integer('class_id')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('announcements')) {
            return;
        }

        Schema::table('announcements', function (Blueprint $table): void {
            if (Schema::hasColumn('announcements', 'attachments')) {
                $table->dropColumn('attachments');
            }

            if (Schema::hasColumn('announcements', 'class_id')) {
                $table->dropColumn('class_id');
            }
        });
    }
};

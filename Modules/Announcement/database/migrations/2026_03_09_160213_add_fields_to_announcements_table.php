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
        $hasPriority = Schema::hasColumn('announcements', 'priority');
        $hasDisplayMode = Schema::hasColumn('announcements', 'display_mode');
        $hasRequiresAcknowledgment = Schema::hasColumn('announcements', 'requires_acknowledgment');
        $hasLink = Schema::hasColumn('announcements', 'link');

        Schema::table('announcements', function (Blueprint $table) use (
            $hasPriority,
            $hasDisplayMode,
            $hasRequiresAcknowledgment,
            $hasLink,
        ): void {
            if (! $hasPriority) {
                $table->enum('priority', ['urgent', 'high', 'medium', 'low'])->default('medium')->after('type');
            }

            if (! $hasDisplayMode) {
                $table->enum('display_mode', ['banner', 'toast', 'modal'])->default('banner')->after('priority');
            }

            if (! $hasRequiresAcknowledgment) {
                $table->boolean('requires_acknowledgment')->default(false)->after('display_mode');
            }

            if (! $hasLink) {
                $table->string('link')->nullable()->after('requires_acknowledgment');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $hasPriority = Schema::hasColumn('announcements', 'priority');
        $hasDisplayMode = Schema::hasColumn('announcements', 'display_mode');
        $hasRequiresAcknowledgment = Schema::hasColumn('announcements', 'requires_acknowledgment');
        $hasLink = Schema::hasColumn('announcements', 'link');

        Schema::table('announcements', function (Blueprint $table) use (
            $hasPriority,
            $hasDisplayMode,
            $hasRequiresAcknowledgment,
            $hasLink,
        ): void {
            if ($hasLink) {
                $table->dropColumn('link');
            }

            if ($hasRequiresAcknowledgment) {
                $table->dropColumn('requires_acknowledgment');
            }

            if ($hasDisplayMode) {
                $table->dropColumn('display_mode');
            }

            if ($hasPriority) {
                $table->dropColumn('priority');
            }
        });
    }
};

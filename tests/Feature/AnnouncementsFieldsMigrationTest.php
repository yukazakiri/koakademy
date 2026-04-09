<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

test('announcements fields migration skips existing columns', function () {
    if (! Schema::hasColumn('announcements', 'priority')) {
        Schema::table('announcements', function (Blueprint $table) {
            $table->enum('priority', ['urgent', 'high', 'medium', 'low'])->default('medium');
        });
    }

    if (! Schema::hasColumn('announcements', 'display_mode')) {
        Schema::table('announcements', function (Blueprint $table) {
            $table->enum('display_mode', ['banner', 'toast', 'modal'])->default('banner');
        });
    }

    if (! Schema::hasColumn('announcements', 'requires_acknowledgment')) {
        Schema::table('announcements', function (Blueprint $table) {
            $table->boolean('requires_acknowledgment')->default(false);
        });
    }

    if (! Schema::hasColumn('announcements', 'link')) {
        Schema::table('announcements', function (Blueprint $table) {
            $table->string('link')->nullable();
        });
    }

    $migration = require database_path('migrations/2026_03_09_160213_add_fields_to_announcements_table.php');

    $migration->up();

    expect(Schema::hasColumn('announcements', 'priority'))->toBeTrue();
    expect(Schema::hasColumn('announcements', 'display_mode'))->toBeTrue();
    expect(Schema::hasColumn('announcements', 'requires_acknowledgment'))->toBeTrue();
    expect(Schema::hasColumn('announcements', 'link'))->toBeTrue();
});

<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

test('pulse migration skips existing tables', function () {
    Schema::dropIfExists('pulse_values');
    Schema::dropIfExists('pulse_entries');
    Schema::dropIfExists('pulse_aggregates');

    Schema::create('pulse_values', function (Blueprint $table) {
        $table->id();
    });

    Schema::create('pulse_entries', function (Blueprint $table) {
        $table->id();
    });

    Schema::create('pulse_aggregates', function (Blueprint $table) {
        $table->id();
    });

    $migration = require database_path('migrations/2026_03_09_105911_create_pulse_tables.php');

    $migration->up();

    expect(Schema::hasTable('pulse_values'))->toBeTrue();
    expect(Schema::hasTable('pulse_entries'))->toBeTrue();
    expect(Schema::hasTable('pulse_aggregates'))->toBeTrue();
});

<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

test('student id change logs migration skips when table already exists', function () {
    Schema::dropIfExists('student_id_change_logs');

    Schema::create('student_id_change_logs', function (Blueprint $table) {
        $table->id();
    });

    $migration = require database_path('migrations/2026_03_06_094140_create_student_id_change_logs_table.php');

    $migration->up();

    expect(Schema::hasTable('student_id_change_logs'))->toBeTrue();
});

<?php

declare(strict_types=1);

use App\Models\AdminTransaction;
use App\Models\AssessmentRevision;
use App\Models\ClassEnrollment;
use App\Models\FeeScheduleVersion;
use App\Models\PaymentAllocation;
use App\Models\StudentEnrollment;
use App\Models\StudentTuition;
use App\Models\SubjectEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('ensures every migration across app and modules implements down()', function (): void {
    $files = array_merge(
        glob(base_path('database/migrations/*.php')) ?: [],
        glob(base_path('Modules/*/database/migrations/*.php')) ?: []
    );

    $missingDown = [];

    foreach ($files as $file) {
        $content = (string) file_get_contents($file);
        if (! preg_match('/function\s+down\b/', $content)) {
            $missingDown[] = basename($file);
        }
    }

    expect($missingDown)->toBeEmpty(
        'The following migrations are missing a down() method: '.json_encode($missingDown, JSON_PRETTY_PRINT)
    );
});

it('casts money attributes to decimal:2 on financial models', function (): void {
    $tuition = new StudentTuition;
    $tuitionCasts = $tuition->getCasts();

    expect($tuitionCasts['total_tuition'])->toBe('decimal:2')
        ->and($tuitionCasts['total_balance'])->toBe('decimal:2')
        ->and($tuitionCasts['total_lectures'])->toBe('decimal:2')
        ->and($tuitionCasts['total_laboratory'])->toBe('decimal:2')
        ->and($tuitionCasts['total_miscelaneous_fees'])->toBe('decimal:2')
        ->and($tuitionCasts['downpayment'])->toBe('decimal:2')
        ->and($tuitionCasts['overall_tuition'])->toBe('decimal:2')
        ->and($tuitionCasts['assessment_adjustment'])->toBe('decimal:2')
        ->and($tuitionCasts['paid'])->toBe('decimal:2')
        ->and($tuitionCasts['gross_lecture'])->toBe('decimal:2');

    $enrollment = new StudentEnrollment;
    expect($enrollment->getCasts()['downpayment'])->toBe('decimal:2');

    $subjectEnrollment = new SubjectEnrollment;
    $subjectCasts = $subjectEnrollment->getCasts();
    expect($subjectCasts['lecture_fee'])->toBe('decimal:2')
        ->and($subjectCasts['laboratory_fee'])->toBe('decimal:2')
        ->and($subjectCasts['grade'])->toBe('decimal:2')
        ->and($subjectCasts['grade_quality_points'])->toBe('decimal:2');

    $paymentAllocation = new PaymentAllocation;
    expect($paymentAllocation->getCasts()['amount'])->toBe('decimal:2');

    $revision = new AssessmentRevision;
    $revisionCasts = $revision->getCasts();
    expect($revisionCasts['total_tuition'])->toBe('decimal:2')
        ->and($revisionCasts['overall_tuition'])->toBe('decimal:2')
        ->and($revisionCasts['balance_due'])->toBe('decimal:2')
        ->and($revisionCasts['total_paid'])->toBe('decimal:2');

    $adminTx = new AdminTransaction;
    expect($adminTx->getCasts()['amount'])->toBe('decimal:2');

    $classEnrollment = new ClassEnrollment;
    $classCasts = $classEnrollment->getCasts();
    expect($classCasts['prelim_grade'])->toBe('decimal:2')
        ->and($classCasts['midterm_grade'])->toBe('decimal:2')
        ->and($classCasts['finals_grade'])->toBe('decimal:2')
        ->and($classCasts['total_average'])->toBe('decimal:2');

    $feeSchedule = new FeeScheduleVersion;
    $feeCasts = $feeSchedule->getCasts();
    expect($feeCasts['lecture_rate_per_unit'])->toBe('decimal:2')
        ->and($feeCasts['laboratory_rate_per_unit'])->toBe('decimal:2')
        ->and($feeCasts['miscellaneous_fee'])->toBe('decimal:2');
});

it('ensures admin_transactions table has status column', function (): void {
    expect(Schema::hasTable('admin_transactions'))->toBeTrue()
        ->and(Schema::hasColumn('admin_transactions', 'status'))->toBeTrue();
});

it('prunes stale database sessions via sessions:prune', function (): void {
    if (! Schema::hasTable('sessions')) {
        Schema::create('sessions', function ($table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    DB::table('sessions')->insert([
        [
            'id' => 'stale_session_1',
            'user_id' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'TestAgent',
            'payload' => 'dummy',
            'last_activity' => now()->subHours(5)->getTimestamp(),
        ],
        [
            'id' => 'active_session_1',
            'user_id' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'TestAgent',
            'payload' => 'dummy',
            'last_activity' => now()->subMinute()->getTimestamp(),
        ],
    ]);

    Artisan::call('sessions:prune', ['--hours' => 2]);

    expect(DB::table('sessions')->where('id', 'stale_session_1')->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'active_session_1')->exists())->toBeTrue();
});

it('prunes read notifications via notifications:prune', function (): void {
    if (! Schema::hasTable('notifications')) {
        Schema::create('notifications', function ($table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    $oldReadId = (string) Str::uuid();
    $recentReadId = (string) Str::uuid();
    $oldUnreadId = (string) Str::uuid();

    DB::table('notifications')->insert([
        [
            'id' => $oldReadId,
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => 1,
            'data' => '{}',
            'read_at' => now()->subDays(40),
            'created_at' => now()->subDays(45),
            'updated_at' => now()->subDays(40),
        ],
        [
            'id' => $recentReadId,
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => 1,
            'data' => '{}',
            'read_at' => now()->subDays(2),
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(2),
        ],
        [
            'id' => $oldUnreadId,
            'type' => 'App\\Notifications\\TestNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => 1,
            'data' => '{}',
            'read_at' => null,
            'created_at' => now()->subDays(50),
            'updated_at' => now()->subDays(50),
        ],
    ]);

    Artisan::call('notifications:prune', ['--days' => 30]);

    expect(DB::table('notifications')->where('id', $oldReadId)->exists())->toBeFalse()
        ->and(DB::table('notifications')->where('id', $recentReadId)->exists())->toBeTrue()
        ->and(DB::table('notifications')->where('id', $oldUnreadId)->exists())->toBeTrue();
});

it('reads ACTIVITY_LOG_ENABLED as a fallback in activitylog config', function (): void {
    $repo = Illuminate\Support\Env::getRepository();
    $repo->clear('ACTIVITY_LOGGER_ENABLED');
    $repo->set('ACTIVITY_LOG_ENABLED', 'false');

    $config = require config_path('activitylog.php');
    expect($config['enabled'])->toBeFalse();

    $repo->set('ACTIVITY_LOG_ENABLED', 'true');
    $config = require config_path('activitylog.php');
    expect($config['enabled'])->toBeTrue();

    // Specific ACTIVITY_LOGGER_ENABLED takes precedence if defined
    $repo->set('ACTIVITY_LOGGER_ENABLED', 'false');
    $repo->set('ACTIVITY_LOG_ENABLED', 'true');
    $config = require config_path('activitylog.php');
    expect($config['enabled'])->toBeFalse();

    // Clean up
    $repo->clear('ACTIVITY_LOGGER_ENABLED');
    $repo->set('ACTIVITY_LOG_ENABLED', 'false');
});

it('executes up and down on the grade constraints and jsonb migration', function (): void {
    $migrationPath = base_path('database/migrations/2026_10_31_000001_add_grade_constraints_jsonb_indexes_and_admin_tx_status.php');
    $migration = require $migrationPath;

    expect($migration)->toBeInstanceOf(Illuminate\Database\Migrations\Migration::class);

    $migration->up();
    expect(Schema::hasColumn('admin_transactions', 'status'))->toBeTrue();

    $migration->down();
    expect(Schema::hasColumn('admin_transactions', 'status'))->toBeFalse();

    // Re-run up to restore clean state
    $migration->up();
    expect(Schema::hasColumn('admin_transactions', 'status'))->toBeTrue();
});

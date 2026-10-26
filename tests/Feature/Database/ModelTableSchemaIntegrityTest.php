<?php

declare(strict_types=1);

use App\Models\ExportJob;
use App\Models\PendingEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Modules\Inventory\Models\InventoryAmendment;
use Modules\Inventory\Models\InventoryProduct;

it('ensures every Eloquent model has a corresponding database table in the schema', function (): void {
    $models = [];
    $collectModels = function (string $dir, string $prefix) use (&$models): void {
        if (! is_dir($dir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php') {
                continue;
            }

            $rel = mb_substr($file->getPathname(), mb_strlen($dir) + 1);
            $class = $prefix.'\\'.str_replace(['/', '.php'], ['\\', ''], $rel);

            if (! class_exists($class)) {
                continue;
            }

            $ref = new ReflectionClass($class);
            if ($ref->isAbstract() || ! $ref->isSubclassOf(Model::class)) {
                continue;
            }

            $models[] = $class;
        }
    };

    $collectModels(app_path('Models'), 'App\\Models');

    foreach (glob(base_path('Modules/*/app/Models')) as $modDir) {
        $modName = basename(dirname(dirname($modDir)));
        $collectModels($modDir, "Modules\\{$modName}\\Models");
        $collectModels($modDir, "Modules\\{$modName}\\app\\Models");
    }

    $models = array_values(array_unique($models));
    expect($models)->not()->toBeEmpty();

    $missingTables = [];
    foreach ($models as $modelClass) {
        $model = new $modelClass;
        $table = $model->getTable();

        if (! Schema::hasTable($table)) {
            $missingTables[$modelClass] = $table;
        }
    }

    expect($missingTables)->toBeEmpty(
        'The following models reference database tables that do not exist: '.json_encode($missingTables, JSON_PRETTY_PRINT)
    );
});

it('verifies export_jobs table and ExportJob model attributes', function (): void {
    expect(Schema::hasTable('export_jobs'))->toBeTrue()
        ->and(Schema::hasColumns('export_jobs', [
            'id', 'job_id', 'user_id', 'export_type', 'filters', 'format',
            'status', 'file_content', 'file_name', 'error_message',
            'started_at', 'completed_at', 'created_at', 'updated_at',
        ]))->toBeTrue();

    $user = User::factory()->create();

    $exportJob = ExportJob::query()->create([
        'job_id' => 'export_test_123',
        'user_id' => $user->id,
        'export_type' => 'student_data',
        'filters' => ['course_filter' => 'BSIT'],
        'format' => 'csv',
        'status' => 'pending',
    ]);

    expect($exportJob->id)->not()->toBeNull()
        ->and($exportJob->job_id)->toBe('export_test_123')
        ->and($exportJob->user->id)->toBe($user->id)
        ->and($exportJob->filters)->toBe(['course_filter' => 'BSIT']);

    $exportJob->markAsProcessing();
    expect($exportJob->fresh()->status)->toBe('processing')
        ->and($exportJob->fresh()->started_at)->not()->toBeNull();

    $exportJob->markAsCompleted('csv,data', 'test.csv');
    expect($exportJob->fresh()->status)->toBe('completed')
        ->and($exportJob->fresh()->file_content)->toBe('csv,data')
        ->and($exportJob->fresh()->file_name)->toBe('test.csv')
        ->and($exportJob->fresh()->completed_at)->not()->toBeNull();
});

it('verifies pending_enrollments table and PendingEnrollment model attributes', function (): void {
    expect(Schema::hasTable('pending_enrollments'))->toBeTrue()
        ->and(Schema::hasColumns('pending_enrollments', [
            'id', 'data', 'status', 'remarks', 'approved_by', 'processed_at',
            'created_at', 'updated_at',
        ]))->toBeTrue();

    $approver = User::factory()->create();

    $pending = PendingEnrollment::query()->create([
        'data' => [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'course_id' => 1,
        ],
        'status' => 'approved',
        'remarks' => 'Approved by registrar',
        'approved_by' => $approver->id,
        'processed_at' => now(),
    ]);

    expect($pending->id)->not()->toBeNull()
        ->and($pending->first_name)->toBe('John')
        ->and($pending->last_name)->toBe('Doe')
        ->and($pending->email)->toBe('john.doe@example.com')
        ->and($pending->course_id)->toBe(1)
        ->and($pending->approver->id)->toBe($approver->id)
        ->and($pending->status)->toBe('approved');
});

it('verifies inventory_amendments table and InventoryAmendment model attributes', function (): void {
    expect(Schema::hasTable('inventory_amendments'))->toBeTrue()
        ->and(Schema::hasColumns('inventory_amendments', [
            'id', 'product_id', 'recorded_quantity', 'actual_quantity', 'variance',
            'status', 'notes', 'amended_by', 'approved_by', 'amendment_date',
            'approved_date', 'created_at', 'updated_at',
        ]))->toBeTrue();

    $amender = User::factory()->create();
    $approver = User::factory()->create();

    $product = InventoryProduct::query()->create([
        'name' => 'Lab Microscope',
        'sku' => 'LAB-MIC-001',
        'unit' => 'pcs',
        'stock_quantity' => 10,
    ]);

    $amendment = InventoryAmendment::query()->create([
        'product_id' => $product->id,
        'recorded_quantity' => 10,
        'actual_quantity' => 8,
        'variance' => -2,
        'status' => 'pending',
        'notes' => 'Damaged lenses found during audit',
        'amended_by' => $amender->id,
        'approved_by' => $approver->id,
        'amendment_date' => now(),
    ]);

    expect($amendment->id)->not()->toBeNull()
        ->and($amendment->product->id)->toBe($product->id)
        ->and($amendment->amendedBy->id)->toBe($amender->id)
        ->and($amendment->approvedBy->id)->toBe($approver->id)
        ->and($amendment->isPending())->toBeTrue()
        ->and($product->amendments)->toHaveCount(1);
});

it('ensures dead models PendingUserEmail and TracksStrand are removed', function (): void {
    expect(file_exists(app_path('Models/PendingUserEmail.php')))->toBeFalse()
        ->and(file_exists(app_path('Models/TracksStrand.php')))->toBeFalse();
});

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_transactions')) {
            return;
        }

        $missingAmount = ! Schema::hasColumn('admin_transactions', 'amount');
        $missingType = ! Schema::hasColumn('admin_transactions', 'type');
        $missingDescription = ! Schema::hasColumn('admin_transactions', 'description');
        $missingStatus = ! Schema::hasColumn('admin_transactions', 'status');

        if (! $missingAmount && ! $missingType && ! $missingDescription && ! $missingStatus) {
            return;
        }

        Schema::table('admin_transactions', function (Blueprint $table) use ($missingAmount, $missingType, $missingDescription, $missingStatus): void {
            if ($missingAmount) {
                $table->decimal('amount', 10, 2)->nullable();
            }

            if ($missingType) {
                $table->string('type')->nullable();
            }

            if ($missingDescription) {
                $table->text('description')->nullable();
            }

            if ($missingStatus) {
                $table->string('status')->default('pending');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_transactions')) {
            return;
        }

        Schema::table('admin_transactions', function (Blueprint $table): void {
            foreach (['amount', 'type', 'description', 'status'] as $column) {
                if (Schema::hasColumn('admin_transactions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

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

        if (! $missingAmount && ! $missingType && ! $missingDescription) {
            return;
        }

        Schema::table('admin_transactions', function (Blueprint $table) use ($missingAmount, $missingType, $missingDescription): void {
            if ($missingAmount) {
                $table->decimal('amount', 10, 2)->nullable();
            }

            if ($missingType) {
                $table->string('type')->nullable();
            }

            if ($missingDescription) {
                $table->text('description')->nullable();
            }
        });
    }

    /**
     * The repair is intentionally non-destructive because canonical installations
     * already owned these columns before this migration ran.
     */
    public function down(): void {}
};

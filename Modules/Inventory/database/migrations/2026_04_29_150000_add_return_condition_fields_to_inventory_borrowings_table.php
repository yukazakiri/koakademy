<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_borrowings')) {
            return;
        }

        Schema::table('inventory_borrowings', function (Blueprint $table): void {
            $table->unsignedInteger('quantity_returned_good')->default(0)->after('quantity_returned');
            $table->unsignedInteger('quantity_returned_defective')->default(0)->after('quantity_returned_good');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventory_borrowings')) {
            return;
        }

        Schema::table('inventory_borrowings', function (Blueprint $table): void {
            $table->dropColumn(['quantity_returned_good', 'quantity_returned_defective']);
        });
    }
};

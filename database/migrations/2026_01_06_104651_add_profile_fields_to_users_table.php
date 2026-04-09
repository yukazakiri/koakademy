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
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 20)->nullable();
            }
            if (! Schema::hasColumn('users', 'address')) {
                $table->string('address')->nullable();
            }
            if (! Schema::hasColumn('users', 'city')) {
                $table->string('city', 100)->nullable();
            }
            if (! Schema::hasColumn('users', 'state')) {
                $table->string('state', 100)->nullable();
            }
            if (! Schema::hasColumn('users', 'country')) {
                $table->string('country', 100)->nullable();
            }
            if (! Schema::hasColumn('users', 'postal_code')) {
                $table->string('postal_code', 20)->nullable();
            }
            if (! Schema::hasColumn('users', 'bio')) {
                $table->text('bio')->nullable();
            }
            if (! Schema::hasColumn('users', 'website')) {
                $table->string('website')->nullable();
            }
            if (! Schema::hasColumn('users', 'department')) {
                $table->string('department')->nullable();
            }
            if (! Schema::hasColumn('users', 'position')) {
                $table->string('position')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // We should only drop columns if we are sure we added them,
            // but for safety in dev environments where they might have existed,
            // we can check if they exist before dropping, although dropColumn usually throws if not exists?
            // Actually dropColumn with array handles it fine in recent Laravel versions or we can be explicit.
            // But since this migration is "add profile fields", down should remove them.
            // However, if they existed BEFORE, we shouldn't remove them.
            // This is tricky. For now, I will leave down empty or comment it out to prevent data loss
            // if the user rolls back and these columns were actually important/pre-existing.
            // Or better, checking if they exist is fine for UP, but DOWN implies we want to revert THIS change.
            // If the columns existed before, this migration did nothing, so down should do nothing.
            // But we can't know if they existed before.
            // I'll stick to standard rollback but wrapped in hasColumn checks to avoid errors.

            $columns = [
                'phone', 'address', 'city', 'state', 'country',
                'postal_code', 'bio', 'website', 'department', 'position',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

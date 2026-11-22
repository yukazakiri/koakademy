<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('connected_accounts')) {
            Schema::table('connected_accounts', function (Blueprint $table): void {
                if (Schema::hasColumn('connected_accounts', 'token')) {
                    $table->text('token')->change();
                }
                if (Schema::hasColumn('connected_accounts', 'secret')) {
                    $table->text('secret')->nullable()->change();
                }
                if (Schema::hasColumn('connected_accounts', 'refresh_token')) {
                    $table->text('refresh_token')->nullable()->change();
                }
            });
        }

        if (Schema::hasTable('accounts') && Schema::hasColumn('accounts', 'two_factor_secret')) {
            Schema::table('accounts', function (Blueprint $table): void {
                $table->text('two_factor_secret')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Keep text columns on rollback to prevent truncating encrypted ciphertexts.
    }
};

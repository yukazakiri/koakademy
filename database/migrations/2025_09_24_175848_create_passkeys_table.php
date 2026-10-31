<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $authenticatableClass = config('auth.providers.users.model', User::class);

        $authenticatableTableName = (new $authenticatableClass)->getTable();

        Schema::create('passkeys', function (Blueprint $table) use ($authenticatableTableName, $authenticatableClass): void {
            $table->id();

            $table
                ->foreignIdFor($authenticatableClass, 'authenticatable_id')
                ->constrained(table: $authenticatableTableName, indexName: 'passkeys_authenticatable_fk')
                ->cascadeOnDelete();

            $table->text('name');
            $table->text('credential_id');
            $table->json('data');

            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passkeys');
    }
};

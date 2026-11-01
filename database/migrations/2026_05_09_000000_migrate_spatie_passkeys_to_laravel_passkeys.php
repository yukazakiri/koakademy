<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Passkeys\Passkeys;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('passkeys')) {
            return;
        }

        if (! Schema::hasColumn('passkeys', 'authenticatable_id') || ! Schema::hasColumn('passkeys', 'data')) {
            return;
        }

        Schema::table('passkeys', function (Blueprint $table): void {
            if (! Schema::hasColumn('passkeys', 'user_id')) {
                $table
                    ->foreignIdFor(Passkeys::userModel(), 'user_id')
                    ->nullable()
                    ->after('authenticatable_id')
                    ->constrained()
                    ->cascadeOnDelete();
            }

            if (! Schema::hasColumn('passkeys', 'credential')) {
                $table->json('credential')->nullable()->after('credential_id');
            }
        });

        DB::table('passkeys')
            ->select(['id', 'authenticatable_id', 'data'])
            ->where(function ($query): void {
                $query
                    ->whereNull('user_id')
                    ->orWhereNull('credential');
            })
            ->orderBy('id')
            ->chunkById(100, function ($passkeys): void {
                foreach ($passkeys as $passkey) {
                    if (! is_string($passkey->data)) {
                        continue;
                    }

                    $credential = json_decode($passkey->data, true);
                    if (! is_array($credential)) {
                        continue;
                    }
                    if (! is_string($credential['publicKeyCredentialId'] ?? null)) {
                        continue;
                    }

                    DB::table('passkeys')
                        ->where('id', $passkey->id)
                        ->update([
                            'user_id' => $passkey->authenticatable_id,
                            'credential_id' => $credential['publicKeyCredentialId'],
                            'credential' => json_encode($credential, JSON_THROW_ON_ERROR),
                        ]);
                }
            });

        Schema::table('passkeys', function (Blueprint $table): void {
            if (Schema::hasColumn('passkeys', 'credential_id')) {
                $table->string('credential_id', 255)->nullable(false)->change();
            }

            $table->unique('credential_id', 'passkeys_credential_id_unique');
            $table->index('user_id', 'passkeys_user_id_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('passkeys') || ! Schema::hasColumn('passkeys', 'authenticatable_id')) {
            return;
        }

        Schema::table('passkeys', function (Blueprint $table): void {
            try {
                $table->dropUnique('passkeys_credential_id_unique');
            } catch (Throwable) {
                // Index may not exist if the migration failed before creating it.
            }

            try {
                $table->dropIndex('passkeys_user_id_index');
            } catch (Throwable) {
                // Index may not exist if the migration failed before creating it.
            }

            if (Schema::hasColumn('passkeys', 'credential')) {
                $table->dropColumn('credential');
            }

            if (Schema::hasColumn('passkeys', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
        });
    }
};

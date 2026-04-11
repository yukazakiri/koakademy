<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->text('signature_path')->nullable()->change();
        });

        // Re-encrypt any existing plaintext signature_path values so
        // the new "encrypted" Eloquent cast can read them correctly.
        $rows = DB::table('students')
            ->whereNotNull('signature_path')
            ->get(['id', 'signature_path']);

        foreach ($rows as $row) {
            $value = $row->signature_path;

            // Skip values that are already encrypted (JSON-encoded with "iv" key).
            $decoded = json_decode($value, true);

            if (is_array($decoded) && isset($decoded['iv'])) {
                continue;
            }

            DB::table('students')
                ->where('id', $row->id)
                ->update(['signature_path' => Crypt::encryptString($value)]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Decrypt values back to plaintext before shrinking the column.
        $rows = DB::table('students')
            ->whereNotNull('signature_path')
            ->get(['id', 'signature_path']);

        foreach ($rows as $row) {
            $value = $row->signature_path;

            try {
                $decrypted = Crypt::decryptString($value);
                DB::table('students')
                    ->where('id', $row->id)
                    ->update(['signature_path' => $decrypted]);
            } catch (Throwable) {
                // Value was already plaintext — leave it as-is.
            }
        }

        Schema::table('students', function (Blueprint $table): void {
            $table->string('signature_path')->nullable()->change();
        });
    }
};

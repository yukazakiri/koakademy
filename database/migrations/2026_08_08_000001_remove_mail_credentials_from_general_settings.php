<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('general_settings')) {
            return;
        }

        $hasSequenzyKey = Schema::hasColumn('general_settings', 'sequenzy_api_key');

        DB::table('general_settings')
            ->select(['id', 'email_settings'])
            ->orderBy('id')
            ->each(function (object $setting) use ($hasSequenzyKey): void {
                $emailSettings = is_string($setting->email_settings)
                    ? json_decode($setting->email_settings, true)
                    : $setting->email_settings;
                $emailSettings = is_array($emailSettings) ? $emailSettings : [];
                $hadPassword = array_key_exists('password', $emailSettings);
                $hadApiKey = array_key_exists('sequenzy_api_key', $emailSettings);

                unset($emailSettings['password'], $emailSettings['sequenzy_api_key']);

                $updates = [];
                if ($hadPassword || $hadApiKey) {
                    $updates['email_settings'] = json_encode($emailSettings, JSON_THROW_ON_ERROR);
                }
                if ($hasSequenzyKey) {
                    $updates['sequenzy_api_key'] = null;
                }

                if ($updates !== []) {
                    DB::table('general_settings')->where('id', $setting->id)->update($updates);
                }
            });
    }

    public function down(): void
    {
        // Secret data is intentionally not recoverable from the application database.
    }
};

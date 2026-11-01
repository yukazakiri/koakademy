<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('mails.database.tables.mails', 'mails'), function (Blueprint $table): void {
            if (! Schema::hasColumn(config('mails.database.tables.mails', 'mails'), 'transport')) {
                $table->string('transport')
                    ->nullable()
                    ->after('mailer');
            }
        });
    }

    public function down(): void
    {
        $mailsTable = config('mails.database.tables.mails', 'mails');
        if (Schema::hasTable($mailsTable)) {
            Schema::table($mailsTable, function (Blueprint $table) use ($mailsTable): void {
                if (Schema::hasColumn($mailsTable, 'transport')) {
                    $table->dropColumn('transport');
                }
            });
        }
    }
};

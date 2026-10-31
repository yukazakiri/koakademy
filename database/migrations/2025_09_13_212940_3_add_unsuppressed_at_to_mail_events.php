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
        Schema::table(config('mails.database.tables.events', 'mail_events'), function (Blueprint $table): void {
            if (! Schema::hasColumn(config('mails.database.tables.events', 'mail_events'), 'unsuppressed_at')) {
                $table->timestamp('unsuppressed_at')
                    ->nullable()
                    ->after('occurred_at');
            }
        });
        Schema::table(config('mails.database.tables.mails', 'mails'), function (Blueprint $table): void {
            if (! Schema::hasColumn(config('mails.database.tables.mails', 'mails'), 'mailer')) {
                $table->string('mailer')
                    ->after('uuid');
            }
            if (! Schema::hasColumn(config('mails.database.tables.mails', 'mails'), 'stream_id')) {
                $table->string('stream_id')
                    ->nullable()
                    ->after('mailer');
            }
        });
    }

    public function down(): void
    {
        $eventsTable = config('mails.database.tables.events', 'mail_events');
        if (Schema::hasTable($eventsTable)) {
            Schema::table($eventsTable, function (Blueprint $table) use ($eventsTable): void {
                if (Schema::hasColumn($eventsTable, 'unsuppressed_at')) {
                    $table->dropColumn('unsuppressed_at');
                }
            });
        }

        $mailsTable = config('mails.database.tables.mails', 'mails');
        if (Schema::hasTable($mailsTable)) {
            Schema::table($mailsTable, function (Blueprint $table) use ($mailsTable): void {
                if (Schema::hasColumn($mailsTable, 'stream_id')) {
                    $table->dropColumn('stream_id');
                }
                if (Schema::hasColumn($mailsTable, 'mailer')) {
                    $table->dropColumn('mailer');
                }
            });
        }
    }
};

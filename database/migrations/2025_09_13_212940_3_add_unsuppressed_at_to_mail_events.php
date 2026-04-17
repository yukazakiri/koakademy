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
};

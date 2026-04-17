<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $mailsTable = config('mails.database.tables.mails', 'mails');
        $eventsTable = config('mails.database.tables.events', 'mail_events');

        Schema::table($mailsTable, function (Blueprint $table) use ($mailsTable) {
            if (! $this->indexExists($mailsTable, 'mails_created_at_index')) {
                $table->index('created_at', 'mails_created_at_index');
            }
            if (! $this->indexExists($mailsTable, 'mails_sent_at_index')) {
                $table->index('sent_at', 'mails_sent_at_index');
            }
        });

        Schema::table($eventsTable, function (Blueprint $table) use ($eventsTable) {
            if (! $this->indexExists($eventsTable, 'mail_events_occurred_at_index')) {
                $table->index('occurred_at', 'mail_events_occurred_at_index');
            }
            if (! $this->indexExists($eventsTable, 'mail_events_type_index')) {
                $table->index('type', 'mail_events_type_index');
            }
        });
    }

    public function down(): void
    {
        $mailsTable = config('mails.database.tables.mails', 'mails');
        $eventsTable = config('mails.database.tables.events', 'mail_events');

        Schema::table($mailsTable, function (Blueprint $table) use ($mailsTable) {
            if ($this->indexExists($mailsTable, 'mails_created_at_index')) {
                $table->dropIndex('mails_created_at_index');
            }
            if ($this->indexExists($mailsTable, 'mails_sent_at_index')) {
                $table->dropIndex('mails_sent_at_index');
            }
        });

        Schema::table($eventsTable, function (Blueprint $table) use ($eventsTable) {
            if ($this->indexExists($eventsTable, 'mail_events_occurred_at_index')) {
                $table->dropIndex('mail_events_occurred_at_index');
            }
            if ($this->indexExists($eventsTable, 'mail_events_type_index')) {
                $table->dropIndex('mail_events_type_index');
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return (bool) DB::selectOne(
            'SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?',
            [$table, $indexName]
        );
    }
};

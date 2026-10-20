<?php

declare(strict_types=1);

use App\Mail\Transports\SequenzyTransport;
use Backstage\Mails\Laravel\Models\Mail as MailLog;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('backfills normalized unique recipients for existing mail logs without changing delivery state', function (): void {
    $id = DB::table('mails')->insertGetId([
        'uuid' => Str::uuid()->toString(),
        'subject' => 'Existing unsent mail',
        'mailer' => 'sequenzy',
        'to' => json_encode([' STUDENT@EXAMPLE.TEST ' => ' Student Name ', '' => null]),
        'cc' => json_encode(['student@example.test' => 'Duplicate', 'REGISTRAR@EXAMPLE.TEST' => null]),
        'bcc' => json_encode(['audit@example.test' => str_repeat('A', 300)]),
        'sent_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $before = (array) DB::table('mails')->find($id);
    $migration = require database_path('migrations/2026_10_20_143000_9_create_mail_recipients_table.php');
    $migration->down();

    $migration->up();

    $recipients = DB::table('mail_recipients')->where('mail_id', $id)->orderBy('email')->get();

    expect($recipients)->toHaveCount(3)
        ->and($recipients->pluck('email')->all())->toBe(['audit@example.test', 'registrar@example.test', 'student@example.test'])
        ->and($recipients->pluck('domain')->unique()->all())->toBe(['example.test'])
        ->and($recipients->firstWhere('email', 'student@example.test')->name)->toBe('student name')
        ->and($recipients->firstWhere('email', 'registrar@example.test')->name)->toBeNull()
        ->and($recipients->firstWhere('email', 'audit@example.test')->name)->toHaveLength(255)
        ->and((array) DB::table('mails')->find($id))->toBe($before);

    foreach (['email', 'name', 'domain'] as $column) {
        expect(Schema::hasIndex('mail_recipients', "mail_recipients_{$column}_index"))->toBeTrue();
    }

    // Deployments that already published the package migration keep their rows.
    $beforeRecipients = $recipients->toJson();
    $migration->up();
    expect(DB::table('mail_recipients')->where('mail_id', $id)->orderBy('email')->get()->toJson())->toBe($beforeRecipients);

    DB::table('mails')->where('id', $id)->delete();
    expect(DB::table('mail_recipients')->where('mail_id', $id)->exists())->toBeFalse();
});

it('respects configured mail and recipient table names when creating and backfilling the lookup', function (): void {
    Schema::create('custom_mail_logs', function (Illuminate\Database\Schema\Blueprint $table): void {
        $table->id();
        $table->json('to')->nullable();
        $table->json('cc')->nullable();
        $table->json('bcc')->nullable();
    });
    config([
        'mails.database.tables.mails' => 'custom_mail_logs',
        'mails.database.tables.recipients' => 'custom_mail_recipients',
    ]);
    $id = DB::table('custom_mail_logs')->insertGetId(['to' => json_encode(['USER@EXAMPLE.TEST' => ' User '])]);
    $migration = require database_path('migrations/2026_10_20_143000_9_create_mail_recipients_table.php');

    $migration->up();

    expect(DB::table('custom_mail_recipients')->where('mail_id', $id)->value('email'))->toBe('user@example.test');

    DB::table('custom_mail_logs')->where('id', $id)->delete();
    expect(DB::table('custom_mail_recipients')->count())->toBe(0);
});

it('logs new mail recipients before reaching the mocked Sequenzy transport', function (): void {
    config(['mail.default' => 'sequenzy', 'mails.logging.enabled' => true]);
    $history = [];
    $handler = HandlerStack::create(new MockHandler([
        new Response(200, ['Content-Type' => 'application/json'], '{"success":true,"jobId":"migration-test-job"}'),
    ]));
    $handler->push(Middleware::history($history));
    $transport = new SequenzyTransport(
        client: new Client(['handler' => $handler]),
        apiKeyResolver: fn (): string => 'test-api-key',
        endpoint: 'https://api.sequenzy.test/api/v1/transactional/send',
    );
    Mail::mailer('sequenzy')->setSymfonyTransport($transport);

    Mail::html('<p>Migration regression test.</p>', function (Illuminate\Mail\Message $message): void {
        $message->to('student@example.test')->subject('Mail recipient migration test');
    });

    $mail = MailLog::query()->where('subject', 'Mail recipient migration test')->sole();
    expect($history)->toHaveCount(1)
        ->and(DB::table('mail_recipients')->where('mail_id', $mail->id)->value('email'))->toBe('student@example.test');

    $mail->update(['to' => ['new@example.test' => 'New recipient']]);
    expect(DB::table('mail_recipients')->where('mail_id', $mail->id)->pluck('email')->all())->toBe(['new@example.test']);
});

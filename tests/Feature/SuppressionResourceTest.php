<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Backstage\Mails\Laravel\Enums\EventType;
use Backstage\Mails\Laravel\Models\Mail;
use Backstage\Mails\Laravel\Models\MailEvent;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

it('loads the suppressions page for postgres-backed mail recipients', function (): void {
    if (! Schema::hasColumn('mail_events', 'unsuppressed_at')) {
        Schema::table('mail_events', function (Blueprint $table): void {
            $table->timestamp('unsuppressed_at')->nullable();
        });
    }

    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $mail = Mail::query()->create([
        'subject' => 'Bounce notice',
        'to' => [
            'bounced@example.com' => 'Bounced Recipient',
        ],
    ]);

    MailEvent::query()->create([
        'mail_id' => $mail->id,
        'type' => EventType::HARD_BOUNCED,
        'occurred_at' => now(),
    ]);

    $this
        ->actingAs($user)
        ->get('https://admin.koakademy.test/admin/mails/suppressions')
        ->assertSuccessful()
        ->assertSee('bounced@example.com');
});

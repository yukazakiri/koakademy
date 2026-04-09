<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\TestDatabaseNotification;
use Illuminate\Console\Command;

final class SendTestNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:test 
                            {user : The ID or email of the user to send the notification to}
                            {--message= : Custom message for the notification}
                            {--title= : Custom title for the notification}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a test database notification to a user';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userIdentifier = $this->argument('user');

        // Find user by ID or email
        $user = is_numeric($userIdentifier)
            ? User::find((int) $userIdentifier)
            : User::where('email', $userIdentifier)->first();

        if (! $user) {
            $this->error("User not found: {$userIdentifier}");

            return self::FAILURE;
        }

        $title = $this->option('title') ?? 'Test Notification';
        $message = $this->option('message') ?? 'This is a test notification sent at '.now()->format('Y-m-d H:i:s');

        $user->notify(new TestDatabaseNotification(
            title: $title,
            message: $message,
            icon: 'heroicon-o-bell',
            type: 'info'
        ));

        $this->info("✓ Test notification sent to {$user->name} ({$user->email})");
        $this->table(
            ['Property', 'Value'],
            [
                ['Title', $title],
                ['Message', $message],
                ['User ID', $user->id],
                ['User Email', $user->email],
            ]
        );

        return self::SUCCESS;
    }
}

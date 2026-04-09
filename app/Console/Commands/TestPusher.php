<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Events\TestNotification;
use Illuminate\Console\Command;

final class TestPusher extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-pusher';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Pusher WebSocket broadcasting';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Testing Pusher configuration...');

        // Show Pusher config
        $this->line('Pusher App ID: '.config('broadcasting.connections.pusher.app_id'));
        $this->line('Pusher Key: '.config('broadcasting.connections.pusher.key'));
        $this->line('Pusher Cluster: '.config('broadcasting.connections.pusher.options.cluster'));
        $this->line('Broadcast Driver: '.config('broadcasting.default'));

        // Test event
        $message = 'Test Pusher notification at '.now()->toDateTimeString();
        $this->info('Sending test event: '.$message);

        event(new TestNotification($message));

        $this->info('Test event dispatched! Check your WebSocket client for the message.');

        return Command::SUCCESS;
    }
}

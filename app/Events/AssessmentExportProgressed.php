<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class AssessmentExportProgressed implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldRescue
{
    use Dispatchable;
    use SerializesModels;

    public string $connection;

    public string $queue;

    /** @param array<string, mixed> $export */
    public function __construct(public int $userId, public array $export)
    {
        $this->connection = (string) config('assessment-exports.connection');
        $this->queue = (string) config('assessment-exports.event_queue');
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'assessment-export.progress';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return ['export' => $this->export];
    }
}

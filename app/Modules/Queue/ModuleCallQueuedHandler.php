<?php

declare(strict_types=1);

namespace App\Modules\Queue;

use App\Modules\ModuleAccessService;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\CallQueuedHandler;
use Illuminate\Queue\Jobs\SyncJob;
use RuntimeException;

final class ModuleCallQueuedHandler extends CallQueuedHandler
{
    protected function dispatchThroughMiddleware(Job $job, $command): mixed
    {
        if (! is_object($command)) {
            return parent::dispatchThroughMiddleware($job, $command);
        }
        $class = match (true) {
            $command instanceof CallQueuedListener => $command->class,
            $command instanceof SendQueuedMailable => $command->mailable::class,
            $command instanceof SendQueuedNotifications => $command->notification::class,
            default => $command::class,
        };
        $access = $this->container->make(ModuleAccessService::class);
        $module = $access->owningClass($class);
        if ($module !== null && ! $access->isAvailable($module->manifest->name)) {
            if ($job instanceof SyncJob) {
                throw new RuntimeException("Extension [{$module->manifest->name}] is unavailable; synchronous work was not executed.");
            }
            // Preserve queued work for a later attempt. Normal retry limits apply.
            $job->release(60);

            return null;
        }

        return parent::dispatchThroughMiddleware($job, $command);
    }
}

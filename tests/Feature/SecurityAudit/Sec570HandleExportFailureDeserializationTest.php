<?php

declare(strict_types=1);

use App\Filament\Handlers\ExportFailureHandler;
use App\Listeners\HandleExportFailure;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;

final class Sec570CanaryGadget
{
    public static bool $triggered = false;

    public function __destruct()
    {
        self::$triggered = true;
    }

    public function __wakeup(): void
    {
        self::$triggered = true;
    }
}

it('never instantiates arbitrary serialized classes in HandleExportFailure', function (): void {
    Sec570CanaryGadget::$triggered = false;

    $craftedPayload = 'O:'.mb_strlen(Sec570CanaryGadget::class).':"'.Sec570CanaryGadget::class.'":0:{}';

    $job = Mockery::mock(Job::class);
    $job->shouldReceive('resolveName')->andReturn('Filament\\Actions\\Exports\\Jobs\\PrepareCsvExport');
    $job->shouldReceive('payload')->andReturn([
        'data' => [
            'command' => $craftedPayload,
        ],
    ]);

    $listener = new HandleExportFailure(new ExportFailureHandler);
    $listener->handle(new JobFailed('sync', $job, new RuntimeException('Export failed')));

    expect(Sec570CanaryGadget::$triggered)->toBeFalse();
});

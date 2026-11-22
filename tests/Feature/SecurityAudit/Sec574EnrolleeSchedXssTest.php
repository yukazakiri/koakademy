<?php

declare(strict_types=1);

it('renders room and section escaped without raw Blade echo in enrollee-sched.blade.php', function (): void {
    $blade = (string) file_get_contents(resource_path('views/infolists/components/enrollee-sched.blade.php'));

    expect($blade)
        ->not->toContain('{!!')
        ->toContain('{{ $schedule[$day][\'room\'] }}')
        ->toContain('{{ $schedule[$day][\'section\'] }}')
        ->toContain('->whereIn(\'id\', $classIds)');
});

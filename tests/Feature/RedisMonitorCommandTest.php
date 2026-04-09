<?php

declare(strict_types=1);

test('redis monitor command succeeds when connections are online', function (): void {
    $this->artisan('redis:monitor')
        ->assertExitCode(0);
});

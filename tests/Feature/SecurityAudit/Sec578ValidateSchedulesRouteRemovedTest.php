<?php

declare(strict_types=1);

it('removes the unauthenticated dead POST /classes/validate-schedules route', function (): void {
    $this->post('/classes/validate-schedules')
        ->assertNotFound();
});

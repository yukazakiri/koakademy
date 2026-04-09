<?php

declare(strict_types=1);

use App\Models\GeneralSetting;

use function Pest\Laravel\get;

it('renders a non-null inertia initial page payload on the login page when analytics is configured', function (): void {
    GeneralSetting::factory()->create([
        'analytics_enabled' => true,
        'analytics_provider' => 'openpanel',
        'analytics_script' => <<<'HTML'
<script>
window.op = window.op || function () {
    return null;
};
</script>
HTML,
        'analytics_settings' => [
            'openpanel_script_url' => 'https://openpanel.dev/op1.js',
            'openpanel_client_id' => 'client-id-123',
            'openpanel_api_url' => 'https://openpanel.koakademy.edu/api',
        ],
    ]);

    config(['inertia.testing.ensure_pages_exist' => false]);

    $response = get(portalUrlForAdministrators('/login'))->assertOk();
    $content = $response->getContent();

    expect($content)
        ->toBeString()
        ->toContain('data-page=')
        ->not->toContain('data-page="null"');
});

<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use App\Services\PdfGenerationService;
use Illuminate\Support\Facades\Route;

it('masks internal 500 exception messages on API routes when app.debug is false', function (): void {
    config(['app.debug' => false]);

    Route::get('/api/_security-test-500', function (): never {
        throw new RuntimeException('SENSITIVE_DB_PASSWORD_OR_SQL_LEAK');
    });

    $response = $this->getJson('/api/_security-test-500');

    $response->assertStatus(500)
        ->assertJson([
            'error' => true,
            'message' => 'An internal server error occurred.',
            'code' => 'SERVER_ERROR',
        ]);

    expect($response->getContent())->not->toContain('SENSITIVE_DB_PASSWORD_OR_SQL_LEAK');
});

it('does not expose stack traces or raw exception details on /test-pdf-generation when app.debug is false', function (): void {
    config(['app.debug' => false]);

    app()->instance(PdfGenerationService::class, new class
    {
        /** @param array<string, mixed> $options */
        public function generatePdfFromHtml(string $html, string $path, array $options = []): never
        {
            throw new Exception('SECRET_CHROME_BINARY_PATH');
        }
    });

    $user = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($user)->getJson('/test-pdf-generation');

    $response->assertStatus(500)
        ->assertJson(['error' => 'PDF generation failed.'])
        ->assertJsonMissingPath('trace')
        ->assertJsonMissingPath('driver');

    expect($response->getContent())->not->toContain('SECRET_CHROME_BINARY_PATH');
});

it('keeps barryvdh/laravel-debugbar in require-dev rather than production require', function (): void {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);

    expect($composer['require'] ?? [])->not->toHaveKey('barryvdh/laravel-debugbar')
        ->and($composer['require-dev'] ?? [])->toHaveKey('barryvdh/laravel-debugbar');
});

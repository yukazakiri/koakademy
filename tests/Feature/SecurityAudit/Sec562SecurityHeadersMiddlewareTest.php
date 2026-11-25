<?php

declare(strict_types=1);

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

it('adds baseline security headers on web routes', function (): void {
    $response = $this->get('/health');

    $response->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'; base-uri 'self'; object-src 'none'");
});

it('adds baseline security headers on API routes', function (): void {
    $response = $this->getJson('/api/v1/public/settings');

    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('adds baseline security headers on Filament panel routes', function (): void {
    $response = $this->get('/admin/login');

    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('emits HSTS on secure production requests', function (): void {
    $originalEnv = app()->environment();
    app()->detectEnvironment(static fn (): string => 'production');

    try {
        $request = Request::create('https://school.example/health', 'GET');
        $middleware = new SecurityHeaders;

        $response = $middleware->handle($request, static fn (): Response => new Response('ok'));

        expect($response->headers->get('Strict-Transport-Security'))
            ->toBe('max-age=31536000; includeSubDomains');
    } finally {
        app()->detectEnvironment(static fn (): string => $originalEnv);
    }
});

it('applies the always directive to security headers in docker/nginx.conf', function (): void {
    $nginx = (string) file_get_contents(base_path('docker/nginx.conf'));

    expect($nginx)
        ->toContain('add_header X-Frame-Options "SAMEORIGIN" always;')
        ->toContain('add_header X-Content-Type-Options "nosniff" always;')
        ->toContain('add_header Referrer-Policy "strict-origin-when-cross-origin" always;');
});

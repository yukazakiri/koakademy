<?php

declare(strict_types=1);

use App\Services\LogoConversionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

it('rejects SVGs containing XML entities, scripts, or external file/http references', function (string $svgPayload): void {
    Storage::fake('public');
    Storage::fake('private');

    $file = UploadedFile::fake()->createWithContent('malicious.svg', $svgPayload);

    expect(fn () => app(LogoConversionService::class)->process($file, 'public'))
        ->toThrow(RuntimeException::class, 'disallowed markup');
})->with([
    'XXE DOCTYPE/ENTITY' => ['<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><svg>&xxe;</svg>'],
    'embedded script' => ['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
    'external file href' => ['<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><image xlink:href="file:///etc/passwd"/></svg>'],
    'external http href' => ['<svg xmlns="http://www.w3.org/2000/svg"><image href="http://169.254.169.254/latest/meta-data/"/></svg>'],
    'event handler attribute' => ['<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><circle r="10"/></svg>'],
]);

it('does not treat text/html files as SVG and cleans up isolated temp directories', function (): void {
    Storage::fake('public');
    Storage::fake('private');

    $htmlFile = UploadedFile::fake()->createWithContent('fake.html', '<html><body>not an image</body></html>');

    expect(fn () => app(LogoConversionService::class)->process($htmlFile, 'public'))
        ->toThrow(RuntimeException::class, 'Unable to read uploaded image');

    $tmpBase = storage_path('framework/logo-tmp');
    if (File::isDirectory($tmpBase)) {
        expect(File::directories($tmpBase))->toBe([]);
    }
});

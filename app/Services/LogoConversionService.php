<?php

declare(strict_types=1);

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class LogoConversionService
{
    /**
     * Process a single logo upload and generate all required formats.
     *
     * Generated outputs stored on the 'r2' disk under a timestamped subdirectory:
     *   - branding/{ts}/logo.png                     (max 512x512)
     *   - branding/{ts}/favicon.ico                   (32x32)
     *   - branding/{ts}/favicon-16x16.png
     *   - branding/{ts}/favicon-32x32.png
     *   - branding/{ts}/favicon-96x96.png
     *   - branding/{ts}/apple-touch-icon.png          (180x180)
     *   - branding/{ts}/web-app-manifest-192x192.png
     *   - branding/{ts}/web-app-manifest-512x512.png
     *   - branding/{ts}/og-image.png                  (1200x630)
     *
     * SVG uploads are rasterized via rsvg-convert before GD processing.
     * The original SVG is also stored verbatim as branding/{ts}/logo.svg.
     *
     * Each upload uses a unique timestamp-based subdirectory so previous uploads
     * are never overwritten — reverting the database leaves old logo files intact.
     *
     * Also copies PWA files to public/ for direct browser access.
     *
     * @return array{logo: string, favicon: string, og_image: string}
     */
    public function process(UploadedFile $file, string $disk = 'r2'): array
    {
        $tmpDir = storage_path('framework/logo-tmp');

        if (! File::isDirectory($tmpDir)) {
            File::makeDirectory($tmpDir, 0755, true);
        }

        $ts = (string) time();
        $prefix = "branding/{$ts}";
        $sourcePath = $file->getRealPath();

        // ── SVG: store original and rasterize to PNG for GD processing ──
        if ($this->isSvg($file)) {
            Storage::disk($disk)->put("{$prefix}/logo.svg", file_get_contents($sourcePath));
            $rasterPath = $tmpDir.'/source-rasterized.png';
            $this->rasterizeSvg($sourcePath, $rasterPath, 512, 512);
            $sourcePath = $rasterPath;
        }

        $sourceImage = $this->loadImage($sourcePath);

        if ($sourceImage === false) {
            throw new RuntimeException('Unable to read uploaded image. Ensure it is a valid PNG, JPG, WEBP, GIF, or SVG.');
        }

        // ── 1. Main logo (512x512 max) ──
        $this->resizeAndSavePng($sourceImage, 512, 512, $tmpDir.'/logo.png');
        Storage::disk($disk)->put("{$prefix}/logo.png", file_get_contents($tmpDir.'/logo.png'));

        // ── 2. Favicon sizes ──
        $faviconSizes = [
            'favicon-16x16.png' => 16,
            'favicon-32x32.png' => 32,
            'favicon-96x96.png' => 96,
        ];

        foreach ($faviconSizes as $name => $size) {
            $this->resizeAndSavePng($sourceImage, $size, $size, $tmpDir.'/'.$name);
            Storage::disk($disk)->put("{$prefix}/{$name}", file_get_contents($tmpDir.'/'.$name));
        }

        // favicon.ico — browsers accept PNG favicons served as ico
        $faviconPath = "{$prefix}/favicon.ico";
        Storage::disk($disk)->put($faviconPath, file_get_contents($tmpDir.'/favicon-32x32.png'));

        // ── 3. Apple touch icon (180x180) ──
        $this->resizeAndSavePng($sourceImage, 180, 180, $tmpDir.'/apple-touch-icon.png');
        Storage::disk($disk)->put("{$prefix}/apple-touch-icon.png", file_get_contents($tmpDir.'/apple-touch-icon.png'));

        // ── 4. PWA manifest icons ──
        $manifestSizes = [
            'web-app-manifest-192x192.png' => 192,
            'web-app-manifest-512x512.png' => 512,
        ];

        foreach ($manifestSizes as $name => $size) {
            $this->resizeAndSavePng($sourceImage, $size, $size, $tmpDir.'/'.$name);
            Storage::disk($disk)->put("{$prefix}/{$name}", file_get_contents($tmpDir.'/'.$name));
        }

        // ── 5. OG image (1200x630, centered logo on dark bg) ──
        $this->createOgImage($sourceImage, $tmpDir.'/og-image.png');
        Storage::disk($disk)->put("{$prefix}/og-image.png", file_get_contents($tmpDir.'/og-image.png'));

        // ── 6. Copy PWA icons to public/ ──
        $this->copyToPublic($tmpDir);

        // ── 7. Cleanup ──
        File::cleanDirectory($tmpDir);

        imagedestroy($sourceImage);

        return [
            'logo' => "{$prefix}/logo.png",
            'favicon' => $faviconPath,
            'og_image' => "{$prefix}/og-image.png",
        ];
    }

    /**
     * Determine whether the uploaded file is an SVG.
     */
    private function isSvg(UploadedFile $file): bool
    {
        $mimeType = $file->getMimeType() ?? '';
        $extension = mb_strtolower($file->getClientOriginalExtension());

        return $mimeType === 'image/svg+xml'
            || $mimeType === 'text/html'  // some servers report SVG as text/html
            || $extension === 'svg';
    }

    /**
     * Rasterize an SVG file to a PNG using rsvg-convert.
     *
     * @throws RuntimeException when rsvg-convert is unavailable or fails.
     */
    private function rasterizeSvg(string $svgPath, string $destPath, int $width, int $height): void
    {
        $rsvg = '/usr/bin/rsvg-convert';

        if (! file_exists($rsvg)) {
            throw new RuntimeException('rsvg-convert is not available. Cannot process SVG uploads.');
        }

        $cmd = sprintf(
            '%s -w %d -h %d -f png -o %s %s 2>&1',
            escapeshellarg($rsvg),
            $width,
            $height,
            escapeshellarg($destPath),
            escapeshellarg($svgPath),
        );

        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0 || ! file_exists($destPath)) {
            throw new RuntimeException('SVG rasterization failed: '.implode(' ', $output));
        }
    }

    /**
     * Load an image from path using GD. Returns false on failure.
     */
    private function loadImage(string $path): GdImage|false
    {
        $info = @getimagesize($path);

        if ($info === false) {
            return false;
        }

        return match ($info[2]) {
            IMAGETYPE_PNG => imagecreatefrompng($path),
            IMAGETYPE_JPEG => imagecreatefromjpeg($path),
            IMAGETYPE_GIF => imagecreatefromgif($path),
            IMAGETYPE_WEBP => imagecreatefromwebp($path),
            default => false,
        };
    }

    /**
     * Resize an image preserving aspect ratio and save as PNG.
     * Never upscale — only shrinks if source is larger.
     */
    private function resizeAndSavePng(GdImage $source, int $maxW, int $maxH, string $destPath): void
    {
        $srcW = imagesx($source);
        $srcH = imagesy($source);

        $ratio = min($maxW / $srcW, $maxH / $srcH, 1.0);
        $newW = (int) round($srcW * $ratio);
        $newH = (int) round($srcH * $ratio);

        $canvas = imagecreatetruecolor($newW, $newH);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);

        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
        imagepng($canvas, $destPath, 9);
        imagedestroy($canvas);
    }

    /**
     * Create a 1200x630 OG image with the logo centered on a dark background.
     */
    private function createOgImage(GdImage $source, string $destPath): void
    {
        $canvas = imagecreatetruecolor(1200, 630);
        $bgColor = imagecolorallocate($canvas, 30, 41, 59); // slate-800
        imagefill($canvas, 0, 0, $bgColor);

        // Resize logo to fit within 400x400, preserving aspect
        $srcW = imagesx($source);
        $srcH = imagesy($source);

        $ratio = min(400 / $srcW, 400 / $srcH, 1.0);
        $logoW = (int) round($srcW * $ratio);
        $logoH = (int) round($srcH * $ratio);

        $logoCanvas = imagecreatetruecolor($logoW, $logoH);
        imagesavealpha($logoCanvas, true);
        $transparent = imagecolorallocatealpha($logoCanvas, 0, 0, 0, 127);
        imagefill($logoCanvas, 0, 0, $transparent);
        imagecopyresampled($logoCanvas, $source, 0, 0, 0, 0, $logoW, $logoH, $srcW, $srcH);

        // Center on canvas
        $x = (int) ((1200 - $logoW) / 2);
        $y = (int) ((630 - $logoH) / 2);
        imagecopy($canvas, $logoCanvas, $x, $y, 0, 0, $logoW, $logoH);

        imagepng($canvas, $destPath, 9);
        imagedestroy($logoCanvas);
        imagedestroy($canvas);
    }

    /**
     * Copy web-accessible icon files to the public directory.
     */
    private function copyToPublic(string $tmpDir): void
    {
        $publicDir = public_path();
        $copies = [
            'favicon-16x16.png',
            'favicon-32x32.png',
            'favicon-96x96.png',
            'apple-touch-icon.png',
            'web-app-manifest-192x192.png',
            'web-app-manifest-512x512.png',
        ];

        foreach ($copies as $filename) {
            $source = $tmpDir.'/'.$filename;
            if (File::exists($source)) {
                File::copy($source, $publicDir.'/'.$filename);
            }
        }

        // Copy 32x32 PNG as favicon.ico
        if (File::exists($tmpDir.'/favicon-32x32.png')) {
            File::copy($tmpDir.'/favicon-32x32.png', $publicDir.'/favicon.ico');
        }

        $this->updateWebManifest($publicDir);
    }

    /**
     * Update the PWA web manifest with current icon paths.
     */
    private function updateWebManifest(string $publicDir): void
    {
        $manifestPath = $publicDir.'/site.webmanifest';

        $manifest = [
            'name' => config('app.name', 'KoAkademy'),
            'short_name' => 'KOA',
            'icons' => [
                [
                    'src' => '/web-app-manifest-192x192.png',
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
                [
                    'src' => '/web-app-manifest-512x512.png',
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
            'theme_color' => '#0f172a',
            'background_color' => '#0f172a',
            'display' => 'standalone',
        ];

        File::put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

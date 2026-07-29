<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

it('allows only the requesting administrator to download a bulk assessment export', function (): void {
    $disk = (string) config('filesystems.default');
    Storage::fake($disk);

    $owner = User::factory()->create(['role' => UserRole::Admin]);
    $otherAdministrator = User::factory()->create(['role' => UserRole::Admin]);
    $jobId = '2f48f77e-3d41-46f5-bf44-e47d36a6579d';
    $filename = 'bulk-assessments-2026-07-29-120000.pdf';
    $path = sprintf('exports/bulk-assessments/%d/%s/%s', $owner->id, $jobId, $filename);

    Storage::disk($disk)->put($path, '%PDF-1.4 test');

    $this->actingAs($owner)
        ->get(portalUrlForAdministrators("/download/bulk-assessment/{$jobId}/{$filename}"))
        ->assertSuccessful()
        ->assertDownload($filename);

    $this->actingAs($otherAdministrator)
        ->get(portalUrlForAdministrators("/download/bulk-assessment/{$jobId}/{$filename}"))
        ->assertNotFound();

    auth()->logout();

    $this->get(portalUrlForAdministrators("/download/bulk-assessment/{$jobId}/{$filename}"))
        ->assertForbidden();
});

<?php

declare(strict_types=1);

use App\Models\StudentEnrollment;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Admin Domain Routes
|--------------------------------------------------------------------------
|
| Routes for the admin subdomain (admin.koakademy.test / admin.koakademy.edu).
| Handles Filament admin panel redirect and assessment downloads.
|
*/

$adminHost = config('app.admin_host');

$registerAdminRoutes = function () use ($adminHost): void {
    if (filled($adminHost) && $adminHost !== config('app.portal_host') && ! in_array($adminHost, ['localhost', '127.0.0.1', ''], true)) {
        Route::get('/', function () {
            return redirect('/admin');
        });
    }

    Route::get('/system-management/brand/appearance', function () {
        return redirect('/administrators/system-management/brand/appearance');
    })->name('admin.system-management.brand.appearance');

    // Assessment download route - accessible from admin domain
    Route::get('assessment/download/{record}', function ($record) {
        abort_unless(Auth::check(), 401);

        // Find the active student enrollment record (excluding soft-deleted)
        $student = StudentEnrollment::where('id', $record)->firstOrFail();

        $user = Auth::user();
        abort_unless($user && ($user->can('View:StudentEnrollment') || $user->can('view', $student)), 403);

        // Get the assessment resource
        $resource = $student
            ->resources()
            ->where('type', 'assessment')
            ->latest('id')
            ->first();

        if (! $resource) {
            abort(404, 'Assessment file not found');
        }

        // Check if file exists on disk (private first, legacy fallback)
        $filePath = $resource->file_path;
        $disk = 'private';
        if (! Storage::disk('private')->exists($filePath)) {
            $legacyDisk = $resource->disk ?? config('filesystems.default');
            if ($legacyDisk !== 'private' && Storage::disk($legacyDisk)->exists($filePath)) {
                $disk = $legacyDisk;
            } elseif (Storage::disk('public')->exists($filePath)) {
                $disk = 'public';
            } else {
                abort(404, 'File not found on disk');
            }
        }

        try {
            // Get the file content
            $fileContent = Storage::disk($disk)->get($filePath);
            $mimeType = Storage::disk($disk)->mimeType($filePath) ?: 'application/pdf';

            // Return file as download
            return response($fileContent)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', 'attachment; filename="'.$resource->file_name.'"')
                ->header('X-Content-Type-Options', 'nosniff');
        } catch (Exception $e) {
            Log::error('Error accessing assessment file', [
                'enrollment_id' => $record,
                'resource_id' => $resource->id,
                'error' => $e->getMessage(),
            ]);
            abort(500, 'Error accessing the file');
        }
    })->middleware(['auth', 'administrators.only'])->name('assessment.download');
};

$isLocalAdminHost = in_array($adminHost, ['localhost', '127.0.0.1', ''], true);

if (! $isLocalAdminHost && filled($adminHost)) {
    Route::domain($adminHost)->group($registerAdminRoutes);
} else {
    Route::group([], $registerAdminRoutes);
}

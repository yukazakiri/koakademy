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

Route::domain(config('app.admin_host', 'admin.koakademy.test'))->group(function () {
    Route::get('/', function () {
        return redirect('/admin');
    });

    // Assessment download route - accessible from admin domain
    Route::get('assessment/download/{record}', function ($record) {
        // Find the student enrollment record
        $student = StudentEnrollment::withTrashed()->where('id', $record)->first();
        if (! $student) {
            abort(404, 'Student enrollment record not found');
        }

        // Get the assessment resource
        $resource = $student
            ->resources()
            ->where('type', 'assessment')
            ->latest()
            ->first();

        if (! $resource) {
            abort(404, 'Assessment file not found');
        }

        // Check if file exists on disk
        $disk = $resource->disk ?? config('filesystems.default');
        if (! Storage::disk($disk)->exists($resource->file_path)) {
            abort(404, 'File not found on disk');
        }

        try {
            // Get the file content
            $fileContent = Storage::disk($disk)->get($resource->file_path);
            $mimeType = Storage::disk($disk)->mimeType($resource->file_path) ?: 'application/pdf';

            // Return file as download
            return response($fileContent)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', 'attachment; filename="'.$resource->file_name.'"');
        } catch (Exception $e) {
            Log::error('Error accessing assessment file', [
                'enrollment_id' => $record,
                'resource_id' => $resource->id,
                'error' => $e->getMessage(),
            ]);
            abort(500, 'Error accessing the file');
        }
    })->name('assessment.download');
});

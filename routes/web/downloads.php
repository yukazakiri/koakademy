<?php

declare(strict_types=1);

use App\Models\Faculty;
use App\Models\Schedule;
use App\Services\PdfGenerationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Download Routes
|--------------------------------------------------------------------------
|
| Routes for downloading files such as student lists, timetable PDFs,
| and schedule exports.
|
*/

// Student list download
Route::get('/download/student-list/{filename}', function (string $filename) {
    $disk = config('filesystems.default');
    $path = 'exports/student-lists/'.$filename;

    if (! Storage::disk($disk)->exists($path)) {
        abort(404, 'File not found on '.$disk.' storage: '.$path);
    }

    $file = Storage::disk($disk)->get($path);
    $mimeType = Storage::disk($disk)->mimeType($path) ?: 'application/pdf';

    return response($file)
        ->header('Content-Type', $mimeType)
        ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
})->name('download.student-list');

// Timetable PDF download
Route::get('/download/timetable-pdf/{filename}', function (string $filename) {
    // Force use R2 disk for timetable PDFs
    $disk = 'r2';

    // Verify R2 disk exists in config, otherwise use default
    if (! config('filesystems.disks.r2')) {
        $disk = config('filesystems.default');
    }

    $path = 'schedules/'.$filename;

    if (! Storage::disk($disk)->exists($path)) {
        // If file doesn't exist, return a simple response
        return response()->json([
            'error' => 'PDF not found. Please contact administrator to generate schedule PDF.',
            'path' => $path,
            'disk' => $disk,
        ], 404);
    }

    // For R2 disk, redirect to a temporary signed URL
    if ($disk === 'r2') {
        $temporaryUrl = Storage::disk($disk)->temporaryUrl(
            $path,
            now()->addMinutes(5)
        );

        return redirect($temporaryUrl);
    }

    // For local/public disks, serve the file directly
    $file = Storage::disk($disk)->get($path);
    $mimeType = Storage::disk($disk)->mimeType($path) ?: 'application/pdf';

    return response($file)
        ->header('Content-Type', $mimeType)
        ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
})->name('download.timetable-pdf');

// Schedule PDF download (authenticated)
Route::get('/download/schedule', function () {
    $user = Auth::user();

    if (! $user || ! $user->isFaculty()) {
        abort(403, 'Unauthorized');
    }

    $faculty = Faculty::where('email', $user->email)->first();

    if (! $faculty) {
        abort(404, 'Faculty profile not found');
    }

    $type = request()->query('type', 'timetable');

    // Fetch schedules with all necessary relationships
    $schedules = Schedule::query()
        ->whereHas('class', function ($query) use ($faculty) {
            $query->where('faculty_id', $faculty->id)
                ->currentAcademicPeriod();
        })
        ->with([
            'class.subject',
            'class.SubjectByCodeFallback',
            'class.ShsSubject',
            'class.Room',
            'class.faculty',
            'room',
        ])
        ->get()
        ->map(function ($schedule) {
            // Standardize the structure for the view
            $class = $schedule->class;

            // Skip if class is null
            if (! $class) {
                return null;
            }

            $subject = $class->subjects->first()
                ?? ($class->isShs() ? $class->ShsSubject : ($class->subject ?: $class->SubjectByCodeFallback));

            $scheduleArray = $schedule->toArray();

            // Ensure class relationship has the resolved subject with fallback values
            if ($subject) {
                $scheduleArray['class']['subject'] = $subject->toArray();
                $scheduleArray['class']['subject']['code'] = $subject->code ?? $class->subject_code ?? 'N/A';
                $scheduleArray['class']['subject']['title'] = $subject->title ?? 'Unknown Subject';
                $scheduleArray['class']['subject']['units'] = $subject->units ?? 0;
            } else {
                // Provide fallback subject data using class properties
                $scheduleArray['class']['subject'] = [
                    'code' => $class->subject_code ?? 'N/A',
                    'title' => $class->subject_title ?? 'Unknown Subject',
                    'units' => 0,
                ];
            }

            // Format times
            $scheduleArray['start_time_formatted'] = $schedule->formatted_start_time;
            $scheduleArray['end_time_formatted'] = $schedule->formatted_end_time;
            $scheduleArray['duration_minutes'] = $schedule->start_time->diffInMinutes($schedule->end_time);

            return $scheduleArray;
        })
        ->filter(); // Remove any null entries from schedules without valid classes

    $viewName = $type === 'matrix' ? 'pdf.subject-matrix-export' : 'pdf.timetable-export';
    $orientation = $type === 'matrix' ? 'landscape' : 'landscape'; // Both usually better in landscape

    $data = [
        'schedules' => $schedules,
        'entityName' => $user->name,
        'currentSchoolYear' => config('app.school_year', '2024-2025'), // Fallback or fetch from settings
        'currentSemester' => config('app.semester', '1st Semester'),
        'selectedView' => $type,
        'days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
        'timeSlots' => [], // Timetable view might need this, can generate if empty
    ];

    // Generate time slots for timetable view if needed
    if ($type === 'timetable') {
        $start = Carbon::parse('07:00');
        $end = Carbon::parse('21:00');
        $slots = [];
        while ($start <= $end) {
            $slots[] = $start->format('H:i');
            $start->addHour();
        }
        $data['timeSlots'] = $slots;
    }

    // Generate PDF using the service
    $pdfService = app(PdfGenerationService::class);
    $fileName = 'schedule-'.$type.'-'.now()->timestamp.'.pdf';
    $tempPath = storage_path('app/temp/'.$fileName);

    // Ensure temp directory exists
    if (! file_exists(dirname($tempPath))) {
        mkdir(dirname($tempPath), 0755, true);
    }

    try {
        $pdfService->generatePdfFromView($viewName, $data, $tempPath, [
            'landscape' => true,
            'format' => 'A4',
            'margins' => ['top' => '10mm', 'right' => '10mm', 'bottom' => '10mm', 'left' => '10mm'],
        ]);

        return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
    } catch (Exception $e) {
        return response()->json([
            'error' => 'Failed to generate PDF: '.$e->getMessage(),
        ], 500);
    }

})->name('download.schedule');

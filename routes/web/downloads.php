<?php

declare(strict_types=1);

use App\Jobs\GenerateTimetablePdfJob;
use App\Models\Faculty;
use App\Models\Schedule;
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
    $disk = config('filesystems.default');

    $path = 'schedules/'.$filename;

    if (! Storage::disk($disk)->exists($path)) {
        // If file doesn't exist, return a simple response
        return response()->json([
            'error' => 'PDF not found. Please contact administrator to generate schedule PDF.',
            'path' => $path,
            'disk' => $disk,
        ], 404);
    }

    // Try to generate a temporary signed URL; fall back to direct download
    try {
        $temporaryUrl = Storage::disk($disk)->temporaryUrl(
            $path,
            now()->addMinutes(5)
        );

        return redirect($temporaryUrl);
    } catch (RuntimeException) {
        // For disks that do not support temporary URLs, serve the file directly
    }
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

    $fileName = 'schedule-'.$type.'-'.now()->timestamp.'.pdf';
    GenerateTimetablePdfJob::dispatch($data, $fileName, $type, (int) $user->id);

    return response()->json([
        'message' => 'Schedule PDF export queued. You will be notified when your file is ready.',
    ], 202);

})->name('download.schedule');

Route::get('/download/attendance-report/{filename}', function (string $filename) {
    $user = Auth::user();

    if (! $user) {
        abort(403, 'Unauthorized');
    }

    $disk = config('filesystems.default');
    $path = 'exports/attendance/'.$user->id.'/'.$filename;

    if (! Storage::disk($disk)->exists($path)) {
        abort(404, 'Attendance report not found.');
    }

    return Storage::disk($disk)->download($path, $filename);
})->name('download.attendance-report');

Route::get('/download/student-soa/{filename}', function (string $filename) {
    $user = Auth::user();

    if (! $user) {
        abort(403, 'Unauthorized');
    }

    $disk = config('filesystems.default');
    $path = 'exports/soa/'.$user->id.'/'.$filename;

    if (! Storage::disk($disk)->exists($path)) {
        abort(404, 'SOA PDF not found.');
    }

    return Storage::disk($disk)->download($path, $filename);
})->name('download.student-soa');

Route::get('/download/enrollment-report/{filename}', function (string $filename) {
    $user = Auth::user();

    if (! $user) {
        abort(403, 'Unauthorized');
    }

    $disk = config('filesystems.default');
    $path = 'exports/enrollment-reports/'.$user->id.'/'.$filename;

    if (! Storage::disk($disk)->exists($path)) {
        abort(404, 'Enrollment report PDF not found.');
    }

    return Storage::disk($disk)->download($path, $filename);
})->name('download.enrollment-report');

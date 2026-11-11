<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\ClassPost;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ClassPostAttachmentController extends Controller
{
    public function downloadPostAttachment(Request $request, Classes $class, string $file): StreamedResponse
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            abort(401);
        }

        $this->authorizeClassAccess($user, $class);

        if (str_contains($file, '..') || str_contains($file, '/') || str_contains($file, '\\')) {
            abort(400, 'Invalid filename.');
        }

        $path = "class-post-attachments/{$file}";

        $disk = 'private';
        if (! Storage::disk('private')->exists($path)) {
            if (Storage::disk('public')->exists($path)) {
                $disk = 'public';
            } elseif (Storage::disk(config('filesystems.default'))->exists($path)) {
                $disk = config('filesystems.default');
            } else {
                abort(404, 'Attachment not found.');
            }
        }

        $originalName = $file;
        $posts = ClassPost::where('class_id', $class->id)->get();
        foreach ($posts as $post) {
            foreach ($post->attachments ?? [] as $att) {
                if (is_array($att)) {
                    $attFilename = basename((string) ($att['path'] ?? parse_url($att['url'] ?? '', PHP_URL_PATH) ?? ''));
                    if ($attFilename === $file) {
                        $originalName = $att['name'] ?? $file;
                        break 2;
                    }
                }
            }
        }

        return Storage::disk($disk)->download($path, $originalName, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function downloadSubmissionAttachment(Request $request, Classes $class, ClassPost $post, string $file): StreamedResponse
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            abort(401);
        }

        if ($post->class_id !== $class->id) {
            abort(404);
        }

        if (str_contains($file, '..') || str_contains($file, '/') || str_contains($file, '\\')) {
            abort(400, 'Invalid filename.');
        }

        $path = "class-post-submissions/{$file}";

        $submissions = $post->submissions()->get();
        $targetSubmission = null;
        $originalName = $file;

        foreach ($submissions as $submission) {
            foreach ($submission->attachments ?? [] as $att) {
                if (is_array($att)) {
                    $attFilename = basename((string) ($att['path'] ?? parse_url($att['url'] ?? '', PHP_URL_PATH) ?? ''));
                    if ($attFilename === $file) {
                        $targetSubmission = $submission;
                        $originalName = $att['name'] ?? $file;
                        break 2;
                    }
                }
            }
        }

        if (! $targetSubmission) {
            abort(404, 'Submission attachment not found.');
        }

        $isAuthorized = false;

        if ($user->canAccessAdminPortal()) {
            $isAuthorized = true;
        } elseif ($user->isFaculty()) {
            $faculty = Faculty::where('email', $user->email)->first();
            if ($faculty && $class->faculty_id === $faculty->id) {
                $isAuthorized = true;
            }
        } elseif ($user->isStudentRole()) {
            $student = Student::where('email', $user->email)
                ->orWhere('user_id', $user->id)
                ->first();
            if ($student && (int) $targetSubmission->student_id === (int) $student->id) {
                $isAuthorized = true;
            }
        }

        if (! $isAuthorized) {
            abort(403);
        }

        $disk = 'private';
        if (! Storage::disk('private')->exists($path)) {
            if (Storage::disk('public')->exists($path)) {
                $disk = 'public';
            } elseif (Storage::disk(config('filesystems.default'))->exists($path)) {
                $disk = config('filesystems.default');
            } else {
                abort(404, 'File not found on storage.');
            }
        }

        return Storage::disk($disk)->download($path, $originalName, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizeClassAccess(User $user, Classes $class): void
    {
        if ($user->canAccessAdminPortal()) {
            return;
        }

        if ($user->isFaculty()) {
            $faculty = Faculty::where('email', $user->email)->first();
            if ($faculty && $class->faculty_id === $faculty->id) {
                return;
            }
        }

        if ($user->isStudentRole()) {
            $student = Student::where('email', $user->email)
                ->orWhere('user_id', $user->id)
                ->first();
            if ($student && ClassEnrollment::where('class_id', $class->id)->where('student_id', $student->id)->exists()) {
                return;
            }
        }

        abort(403);
    }
}

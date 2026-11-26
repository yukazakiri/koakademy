<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StudentType;
use App\Mail\StudentIdentifierRecoveryMail;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class SignupIdentifierRecoveryController extends Controller
{
    public const string GENERIC_MESSAGE = 'If a matching student record exists, we have sent your Student Number / LRN to that email address.';

    public function __invoke(Request $request): JsonResponse
    {
        $request->merge([
            'email' => $request->string('email')->trim()->lower()->toString(),
        ]);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = (string) $validated['email'];

        $existingUser = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->exists();

        if (! $existingUser) {
            $students = Student::withoutSchoolScope()
                ->with(['Course' => fn ($query) => $query->withoutSchoolScope()])
                ->whereNull('user_id')
                ->whereRaw('LOWER(email) = ?', [$email])
                ->where(function ($query): void {
                    $query->whereNotNull('student_id')
                        ->orWhereNotNull('lrn');
                })
                ->orderBy('id')
                ->get();

            $records = $students
                ->map(function (Student $student): ?array {
                    $studentId = filled($student->student_id) ? (string) $student->student_id : null;
                    $lrn = filled($student->lrn) ? (string) $student->lrn : null;

                    if ($studentId === null && $lrn === null) {
                        return null;
                    }

                    $studentType = $student->student_type instanceof StudentType
                        ? $student->student_type->getLabel()
                        : (is_string($student->student_type) ? ucfirst($student->student_type) : null);

                    return [
                        'student_name' => (string) $student->full_name,
                        'student_id' => $studentId,
                        'lrn' => $lrn,
                        'student_type' => $studentType,
                        'program' => $student->Course?->title ?? $student->Course?->code,
                    ];
                })
                ->filter()
                ->values()
                ->all();

            if ($records !== []) {
                try {
                    Mail::to($email)->send(new StudentIdentifierRecoveryMail($email, $records));
                } catch (Throwable $exception) {
                    Log::warning('Failed to send student identifier recovery email.', [
                        'email' => $email,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        }

        return response()->json([
            'message' => self::GENERIC_MESSAGE,
        ]);
    }
}

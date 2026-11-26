<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StudentType;
use App\Mail\FacultyIdentifierRecoveryMail;
use App\Mail\StudentIdentifierRecoveryMail;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\User;
use App\Services\FacultySignupService;
use App\Services\StudentConfigurationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class SignupIdentifierRecoveryController extends Controller
{
    public const string GENERIC_MESSAGE = 'If a matching student record exists, we have sent your student identifier to that email address.';

    public function __invoke(
        Request $request,
        StudentConfigurationService $studentConfig,
        FacultySignupService $facultySignup,
    ): JsonResponse {
        $request->merge([
            'email' => $request->string('email')->trim()->lower()->toString(),
        ]);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'user_type' => ['sometimes', 'nullable', 'in:student,faculty'],
        ]);

        $email = (string) $validated['email'];
        $userType = (string) ($validated['user_type'] ?? 'student');
        $studentConfig->resolvePublicSchool();
        $publicPresentation = $studentConfig->signupPresentation();

        $existingUser = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->exists();

        if ($userType === 'faculty') {
            $publicFacultyLabel = (string) ($publicPresentation['faculty_id_label'] ?? 'Faculty ID');

            if (! $existingUser) {
                $faculties = $facultySignup->candidatesForEmail($email);
                $faculties->loadMissing('school');

                $eligibleFaculties = $faculties
                    ->filter(function (Faculty $faculty) use ($studentConfig): bool {
                        if (! $faculty->isRegisterable() || ! filled($faculty->faculty_id_number)) {
                            return false;
                        }

                        $config = $studentConfig->get($faculty->school_id);
                        $allowRecovery = (bool) ($config['signup']['allow_identifier_recovery'] ?? true);
                        $allowSelfSignup = (bool) ($config['signup']['allow_faculty_self_signup'] ?? true);

                        return $allowRecovery && $allowSelfSignup;
                    })
                    ->values();

                if ($eligibleFaculties->isNotEmpty()) {
                    $claimedRecordIds = User::query()
                        ->whereIn('record_id', $eligibleFaculties->pluck('id')->map(fn (mixed $id): string => (string) $id)->all())
                        ->pluck('record_id')
                        ->map(fn (mixed $id): string => (string) $id)
                        ->all();

                    if ($claimedRecordIds !== []) {
                        $eligibleFaculties = $eligibleFaculties
                            ->reject(fn (Faculty $faculty): bool => in_array((string) $faculty->id, $claimedRecordIds, true))
                            ->values();
                    }
                }

                $records = $eligibleFaculties
                    ->map(function (Faculty $faculty) use ($studentConfig): array {
                        $presentation = $studentConfig->signupPresentation($faculty->school_id);

                        return [
                            'faculty_name' => (string) $faculty->full_name,
                            'faculty_id_label' => (string) ($presentation['faculty_id_label'] ?? 'Faculty ID'),
                            'faculty_id_number' => (string) $faculty->faculty_id_number,
                            'department' => filled($faculty->department) ? (string) $faculty->department : null,
                            'school_name' => $faculty->school?->name,
                        ];
                    })
                    ->all();

                if ($records !== []) {
                    $firstSchoolId = $eligibleFaculties->first()?->school_id;
                    $mailIdentifierLabel = (string) ($studentConfig->signupPresentation($firstSchoolId)['faculty_id_label'] ?? $publicFacultyLabel);

                    try {
                        Mail::to($email)->send(new FacultyIdentifierRecoveryMail($email, $records, $mailIdentifierLabel));
                    } catch (Throwable $exception) {
                        Log::warning('Failed to send faculty identifier recovery email.', [
                            'email' => $email,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }
            }

            return response()->json([
                'message' => "If a matching faculty record exists, we have sent your {$publicFacultyLabel} to that email address.",
            ]);
        }

        $publicIdentifierLabel = (string) ($publicPresentation['universal_identifier_label'] ?? 'Student ID');

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
                ->map(function (Student $student) use ($studentConfig): ?array {
                    $config = $studentConfig->get($student->school_id);
                    if (! (bool) ($config['signup']['allow_identifier_recovery'] ?? true)) {
                        return null;
                    }

                    $presentation = $studentConfig->signupPresentation($student->school_id);
                    $studentIdLabel = (string) ($presentation['student_id_label'] ?? 'Student ID');
                    $externalConfig = $student->student_type instanceof StudentType
                        ? $studentConfig->externalFor($student->student_type->value, $student->school_id)
                        : ($config['external_identifier'] ?? []);
                    $externalEnabled = (bool) ($externalConfig['enabled'] ?? false);
                    $externalLabel = (string) ($externalConfig['label'] ?? 'External Identifier');

                    $studentId = filled($student->student_id) ? (string) $student->student_id : null;
                    $externalId = ($externalEnabled && filled($student->lrn)) ? (string) $student->lrn : null;

                    if ($studentId === null && $externalId === null) {
                        return null;
                    }

                    $studentTypeValue = $student->student_type instanceof StudentType
                        ? $student->student_type->value
                        : (is_string($student->student_type) ? $student->student_type : null);
                    $studentTypeLabel = $studentTypeValue !== null
                        ? (string) ($config['type_labels'][$studentTypeValue] ?? ($student->student_type instanceof StudentType ? $student->student_type->getLabel() : ucfirst($studentTypeValue)))
                        : null;

                    return [
                        'student_name' => (string) $student->full_name,
                        'student_id_label' => $studentIdLabel,
                        'student_id' => $studentId,
                        'external_id_label' => $externalLabel,
                        'external_id' => $externalId,
                        'student_type' => $studentTypeLabel,
                        'program' => $student->Course?->title ?? $student->Course?->code,
                    ];
                })
                ->filter()
                ->values()
                ->all();

            if ($records !== []) {
                $firstSchoolId = $students->first()?->school_id;
                $mailIdentifierLabel = (string) ($studentConfig->signupPresentation($firstSchoolId)['universal_identifier_label'] ?? $publicIdentifierLabel);

                try {
                    Mail::to($email)->send(new StudentIdentifierRecoveryMail($email, $records, $mailIdentifierLabel));
                } catch (Throwable $exception) {
                    Log::warning('Failed to send student identifier recovery email.', [
                        'email' => $email,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        }

        return response()->json([
            'message' => "If a matching student record exists, we have sent your {$publicIdentifierLabel} to that email address.",
        ]);
    }
}

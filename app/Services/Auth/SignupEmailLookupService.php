<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Services\FacultySignupService;
use App\Services\StudentConfigurationService;
use App\Services\StudentOrganizationAssignmentService;

final class SignupEmailLookupService
{
    public function __construct(
        private readonly StudentOrganizationAssignmentService $studentOrganizations,
        private readonly FacultySignupService $facultySignups,
        private readonly StudentConfigurationService $studentConfig,
    ) {}

    /**
     * Resolve whether an email can self-activate as a student or faculty member.
     *
     * @return array<string, mixed>
     */
    public function lookup(string $email, bool $includeHints = false): array
    {
        $normalizedEmail = mb_strtolower(mb_trim($email));

        if ($normalizedEmail === '') {
            return [
                'found' => false,
                'message' => 'Email not found in our records. Please use your registered school email.',
            ];
        }

        $existingUser = User::whereRaw('LOWER(email) = ?', [$normalizedEmail])->first();
        if ($existingUser instanceof User) {
            return [
                'found' => false,
                'message' => 'An account with this email already exists. Please login.',
                'account_exists' => true,
            ];
        }

        $facultyCandidates = $this->facultySignups->candidatesForEmail($normalizedEmail);
        $registerableFaculties = $facultyCandidates->filter(fn ($candidate): bool => $candidate->isRegisterable())->values();

        if ($registerableFaculties->isNotEmpty()) {
            $selfSignupAllowedFaculties = $registerableFaculties->filter(
                fn ($candidate): bool => (bool) ($this->studentConfig->get($candidate->school_id)['signup']['allow_faculty_self_signup'] ?? true),
            )->values();

            if ($selfSignupAllowedFaculties->isEmpty()) {
                return [
                    'found' => false,
                    'self_signup_disabled' => true,
                    'message' => 'Self-service faculty account activation is currently disabled for your institution. Please contact your school administrator.',
                ];
            }

            $payload = [
                'found' => true,
                'type' => 'faculty',
            ];

            if ($includeHints) {
                $payload['hints'] = $this->facultySignups->signupHints($selfSignupAllowedFaculties);
            }

            return $payload;
        }

        $students = $this->studentOrganizations->candidatesForEmail($normalizedEmail);
        $student = $students->first();

        if ($student) {
            $selfSignupAllowedStudents = $students->filter(
                fn ($candidate): bool => (bool) ($this->studentConfig->get($candidate->school_id)['signup']['allow_student_self_signup'] ?? true),
            )->values();

            if ($selfSignupAllowedStudents->isEmpty()) {
                return [
                    'found' => false,
                    'self_signup_disabled' => true,
                    'message' => 'Self-service student account activation is currently disabled for your institution. Please contact your school administrator.',
                ];
            }

            $payload = [
                'found' => true,
                'type' => 'student',
            ];

            if ($includeHints) {
                $payload['hints'] = $this->studentOrganizations->signupHints($selfSignupAllowedStudents);
            }

            return $payload;
        }

        if ($facultyCandidates->isNotEmpty()) {
            return [
                'found' => false,
                'inactive_faculty' => true,
                'message' => 'This faculty record is not currently active. Please contact the registrar.',
            ];
        }

        return [
            'found' => false,
            'message' => 'Email not found in our records. Please use your registered school email.',
        ];
    }
}

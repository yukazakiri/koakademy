<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StudentStatus;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\GeneralSetting;
use App\Models\Student;
use App\Models\User;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Exception;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;

final class DigitalIdCardService
{
    /**
     * Generate a QR code for the given ID card data.
     *
     * @param  array<string, mixed>  $data
     */
    public function generateQrCode(array $data, int $size = 500): string
    {
        $payload = $this->encryptPayload($data);
        $verificationUrl = URL::signedRoute('id-card.verify', [
            'token' => $payload,
        ]);

        $result = new Builder(
            writer: new PngWriter(),
            writerOptions: [],
            validateResult: false,
            data: $verificationUrl,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: 2,
            roundBlockSizeMode: RoundBlockSizeMode::Margin
        )->build();

        return 'data:image/png;base64,'.base64_encode($result->getString());
    }

    /**
     * Generate student ID card data.
     *
     * @return array<string, mixed>
     */
    public function generateStudentIdCard(Student $student): array
    {
        $generalSettings = GeneralSetting::query()->first();
        $currentSchoolYear = $generalSettings?->getSchoolYearString() ?? 'N/A';
        $currentSemester = $generalSettings?->getSemester() ?? '1st Semester';

        $photo = $student->picture1x1 ?? $student->profile_url ?? null;

        $idData = [
            'type' => 'student',
            'id' => $student->student_id,
            'record_id' => $student->id,
            'name' => $student->full_name,
            'email' => $student->email,
            'course' => $student->Course instanceof Course ? $student->Course->code : 'N/A',
            'course_title' => $student->Course instanceof Course ? $student->Course->title : 'N/A',
            'year_level' => $student->academic_year,
            'student_type' => $student->student_type?->value ?? 'College',
            'status' => $student->status?->value ?? 'Enrolled',
            'school_year' => $currentSchoolYear,
            'semester' => $currentSemester,
            'issued_at' => now()->toIso8601String(),
        ];

        $qrCode = $this->generateQrCode([
            'type' => 'student',
            'id' => $student->student_id,
            'record_id' => $student->id,
            'issued_at' => now()->timestamp,
        ]);

        return [
            'card_data' => $idData,
            'photo_url' => $photo,
            'qr_code' => $qrCode,
            'is_valid' => $student->status === StudentStatus::Enrolled,
        ];
    }

    /**
     * Generate faculty ID card data.
     *
     * @return array<string, mixed>
     */
    public function generateFacultyIdCard(Faculty $faculty): array
    {
        $generalSettings = GeneralSetting::query()->first();
        $currentSchoolYear = $generalSettings?->getSchoolYearString() ?? 'N/A';

        $idData = [
            'type' => 'faculty',
            'id' => $faculty->faculty_id_number,
            'faculty_id_number' => $faculty->faculty_id_number,
            'record_id' => $faculty->id,
            'name' => $faculty->full_name,
            'email' => $faculty->email,
            'department' => $faculty->department ?? 'N/A',
            'position' => $faculty->position ?? 'Faculty Member',
            'phone_number' => $faculty->phone_number ?? null,
            'specialization' => $faculty->specialization ?? null,
            'status' => $faculty->status ?? 'Active',
            'school_year' => $currentSchoolYear,
            'valid_until' => 'End of '.($currentSchoolYear ?? 'Academic Year'),
            'issued_at' => now()->toIso8601String(),
        ];

        $qrCode = $this->generateQrCode([
            'type' => 'faculty',
            'id' => $faculty->faculty_id_number,
            'record_id' => $faculty->id,
            'issued_at' => now()->timestamp,
        ]);

        // Try to get photo from Faculty record first
        $photoUrl = $faculty->photo_url;

        // If no faculty photo, try to get from User record
        if (! $photoUrl) {
            $user = User::where('email', $faculty->email)->first();
            if ($user) {
                $photoUrl = $user->getFilamentAvatarUrl();
            }
        }

        // Final fallback to Faculty's default (Gravatar)
        if (! $photoUrl) {
            $photoUrl = $faculty->getFilamentAvatarUrl();
        }

        return [
            'card_data' => $idData,
            'photo_url' => $photoUrl,
            'qr_code' => $qrCode,
            'is_valid' => $faculty->status === 'active' || $faculty->status === 'Active',
        ];
    }

    /**
     * Generate ID card data for a user based on their role.
     *
     * @return array<string, mixed>|null
     */
    public function generateIdCardForUser(User $user): ?array
    {
        // Check if user is a student
        $student = Student::where('email', $user->email)
            ->orWhere('user_id', $user->id)
            ->first();

        if ($student instanceof Student) {
            return $this->generateStudentIdCard($student);
        }

        // Check if user is faculty
        $faculty = Faculty::where('email', $user->email)->first();

        if ($faculty instanceof Faculty) {
            return $this->generateFacultyIdCard($faculty);
        }

        return null;
    }

    /**
     * Verify an ID card token.
     *
     * @return array<string, mixed>|null
     */
    public function verifyToken(string $token): ?array
    {
        try {
            $data = $this->decryptPayload($token);

            if (! isset($data['type'], $data['id'], $data['record_id'])) {
                return null;
            }

            $isValid = false;
            $personData = null;

            if ($data['type'] === 'student') {
                $student = Student::find($data['record_id']);
                if ($student instanceof Student && (int) $student->student_id === (int) $data['id']) {
                    $isValid = $student->status === StudentStatus::Enrolled;
                    $course = $student->Course;
                    $personData = [
                        'type' => 'student',
                        'id' => $student->student_id,
                        'name' => $student->full_name,
                        'email' => $student->email,
                        'course' => $course instanceof Course ? $course->code : 'N/A',
                        'year_level' => $student->academic_year,
                        'status' => $student->status?->value ?? 'Unknown',
                        'photo_url' => $student->picture1x1 ?? $student->profile_url ?? null,
                    ];
                }
            } elseif ($data['type'] === 'faculty') {
                $faculty = Faculty::find($data['record_id']);
                if ($faculty instanceof Faculty && $faculty->faculty_id_number === $data['id']) {
                    $isValid = $faculty->status === 'active' || $faculty->status === 'Active';
                    $personData = [
                        'type' => 'faculty',
                        'id' => $faculty->faculty_id_number,
                        'name' => $faculty->full_name,
                        'email' => $faculty->email,
                        'department' => $faculty->department ?? 'N/A',
                        'status' => $faculty->status ?? 'Unknown',
                        'photo_url' => $faculty->photo_url,
                    ];
                }
            }

            if (! $personData) {
                return null;
            }

            // Check if the token is not too old (24 hours for display, but always valid for scan)
            $issuedAt = $data['issued_at'] ?? 0;
            $isExpiredForDisplay = (now()->timestamp - $issuedAt) > 86400; // 24 hours

            return [
                'valid' => $isValid,
                'data' => $personData,
                'issued_at' => date('Y-m-d H:i:s', $issuedAt),
                'is_stale' => $isExpiredForDisplay,
            ];
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Encrypt payload for QR code.
     *
     * @param  array<string, mixed>  $data
     */
    private function encryptPayload(array $data): string
    {
        return Crypt::encryptString(json_encode($data));
    }

    /**
     * Decrypt payload from QR code.
     *
     * @return array<string, mixed>
     */
    private function decryptPayload(string $payload): array
    {
        $decrypted = Crypt::decryptString($payload);

        return json_decode($decrypted, true) ?? [];
    }
}

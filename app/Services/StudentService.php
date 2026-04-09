<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StudentStatus;
use App\Enums\StudentType;
use App\Models\Student;
use App\Models\StudentClearance;
use App\Models\User; // Import User model
use Carbon\Carbon;
use Exception;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class StudentService
{
    /**
     * Creates a new student record along with associated (optional) relations.
     *
     * @param  array  $data  Form data for the student.
     * @return Student|null The created student instance or null on failure.
     */
    public function createStudent(array $data): ?Student
    {
        DB::beginTransaction();
        try {
            // Get student type and generate appropriate ID
            $studentType = isset($data['student_type'])
                ? StudentType::from($data['student_type'])
                : StudentType::College;

            $newStudentId = Student::generateNextId($studentType);

            // Calculate age from birth_date
            $birthDate = Carbon::parse($data['birth_date']);
            $age = $birthDate->age;

            // Get student status (default to Enrolled if not provided)
            $status = $data['status'] ?? StudentStatus::Enrolled->value;

            // --- Create related records (if necessary/data provided) ---
            // For now, we'll assume these are optional or handled elsewhere,
            // focusing on the core student creation and the age fix.
            // If these become required, their creation logic would go here.
            // $contact = StudentContact::create([...]);
            // $parentInfo = StudentParentsInfo::create([...]);
            // $educationInfo = StudentEducationInfo::create([...]);
            // $personalInfo = StudentsPersonalInfo::create([...]);
            // ---

            $student = Student::query()->create([
                'student_id' => $newStudentId,
                'institution_id' => $data['institution_id'] ?? 1,
                'lrn' => $data['lrn'] ?? null,
                'student_type' => $studentType->value,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'email' => $data['email'],
                'course_id' => $data['course_id'],
                'academic_year' => $data['academic_year'], // This is the *starting* academic year
                'gender' => $data['gender'],
                'birth_date' => $data['birth_date'],
                'age' => $age, // Add the calculated age
                'status' => $status, // Use the status from form or default
                // 'clearance_status' => 'pending', // Using new clearance system instead
                // Add defaults or nulls for other potentially required fields if needed
                // 'student_contact_id' => $contact->id ?? null,
                // 'student_parent_info' => $parentInfo->id ?? null,
                // 'student_education_id' => $educationInfo->id ?? null,
                // 'student_personal_id' => $personalInfo->id ?? null,
            ]);

            // Create a clearance record for the new student
            StudentClearance::createForCurrentSemester($student);

            DB::commit();

            Notification::make()
                ->success()
                ->title('Student Created')
                ->body(sprintf('Student %s (ID: %s) created successfully.', $student->full_name, $student->student_id))
                ->sendToDatabase(User::role('super_admin')->get()) // Send to database for super admins
                ->send(); // Also send regular notification

            return $student;

        } catch (Exception $exception) {
            DB::rollBack();
            Log::error('Student Creation Failed: '.$exception->getMessage(), ['data' => $data, 'exception' => $exception]); // Log detailed error

            Notification::make()
                ->danger()
                ->title('Student Creation Failed')
                // Provide a more user-friendly error, log the technical details
                ->body('Could not create the new student record. Error: '.$exception->getMessage()) // Simplified body for DB notification
                ->sendToDatabase(User::role('super_admin')->get()) // Send to database for super admins
                ->body('Could not create the new student record. Please check the logs for details. Error: '.$exception->getMessage()) // Restore detailed body for regular notification
                ->persistent()
                ->send(); // Also send regular notification

            return null; // Indicate failure
        }
    }
}

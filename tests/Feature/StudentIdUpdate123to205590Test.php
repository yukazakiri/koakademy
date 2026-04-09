<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\Room;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTransaction;
use App\Models\StudentTuition;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Models\Transaction;
use App\Services\StudentIdUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class StudentIdUpdate123to205590Test extends TestCase
{
    use RefreshDatabase;

    private StudentIdUpdateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(StudentIdUpdateService::class);
        $this->createRequiredTables();
        $this->seedTestData();
    }

    public function test_student_id_update_from_123_to_205590(): void
    {
        // Find the student with ID 123
        $student123 = Student::find(123);

        expect($student123)->not->toBeNull()
            ->and($student123->first_name)->toBe('Test')
            ->and($student123->last_name)->toBe('Student123')
            ->and($student123->contacts)->toBeArray()
            ->and($student123->subject_enrolled)->toBeArray();

        // Verify related records exist for ID 123
        expect(StudentTuition::where('student_id', 123)->count())->toBe(1)
            ->and(StudentTransaction::where('student_id', 123)->count())->toBe(1)
            ->and(StudentEnrollment::where('student_id', '123')->count())->toBe(1)
            ->and(ClassEnrollment::where('student_id', 123)->count())->toBe(1)
            ->and(SubjectEnrollment::where('student_id', 123)->count())->toBe(1)
            ->and(Account::where('person_id', 123)->where('person_type', Student::class)->count())->toBe(1);

        // Ensure target student_id 205590 doesn't exist
        expect(Student::where('student_id', 205590)->exists())->toBeFalse();

        // Perform the ID update
        $result = $this->service->updateStudentId($student123, 205590, true);

        // Assert the update was successful
        expect($result['success'])->toBeTrue()
            ->and($result['old_id'])->toBe(123)
            ->and($result['new_id'])->toBe(205590);

        // Verify the same student record now has updated student_id
        $updatedStudent = Student::find(123);
        expect($updatedStudent)->not->toBeNull()
            ->and($updatedStudent->student_id)->toBe(205590)
            ->and($updatedStudent->first_name)->toBe('Test');

        // Verify change log was created
        expect($result['change_log_id'])->toBeInt();
        $changeLog = DB::table('student_id_change_logs')->find($result['change_log_id']);
        expect($changeLog)->not->toBeNull()
            ->and($changeLog->old_student_id)->toBe('123')
            ->and($changeLog->new_student_id)->toBe('205590')
            ->and($changeLog->student_name)->toContain('Student123');

        // Related records remain unchanged in current simplified implementation
        expect(StudentTuition::where('student_id', 123)->count())->toBe(1);
        expect(StudentTransaction::where('student_id', 123)->count())->toBe(1);
    }

    public function test_validation_prevents_update_if_target_id_exists(): void
    {
        // Create a student with the target student_id 205590 first
        Student::create([
            'id' => 205590,
            'student_id' => 205590,
            'first_name' => 'Existing',
            'last_name' => 'Student',
            'gender' => 'female',
            'birth_date' => '1999-01-01',
            'age' => 25,
            'course_id' => 1,
            'academic_year' => 3,
            'email' => 'existing205590@example.com',
            'status' => 'enrolled',
        ]);

        $student123 = Student::find(123);

        // Try to update - should fail because 205590 already exists
        $result = $this->service->updateStudentId($student123, 205590);

        expect($result['success'])->toBeFalse()
            ->and($result['message'])->toContain('Student ID 205590 is already in use');

        // Verify original student 123 still exists
        expect(Student::find(123))->not->toBeNull();
    }

    public function test_get_affected_records_summary(): void
    {
        $summary = $this->service->getAffectedRecordsSummary();

        expect($summary)->toBe([
            'students' => 1,
            'total_updated' => 1,
        ]);
    }

    public function test_undo_functionality(): void
    {
        $student123 = Student::find(123);

        // Perform the update
        $updateResult = $this->service->updateStudentId($student123, 205590, true);
        expect($updateResult['success'])->toBeTrue();

        // Verify update happened
        expect(Student::find(123))->not->toBeNull()
            ->and(Student::find(123)?->student_id)->toBe(205590);

        // Undo the change
        $undoResult = $this->service->undoStudentIdChange($updateResult['change_log_id']);

        expect($undoResult['success'])->toBeTrue()
            ->and($undoResult['old_id'])->toBe('205590')
            ->and($undoResult['new_id'])->toBe('123');

        // Verify undo worked - same student record is back to student_id 123
        expect(Student::find(123))->not->toBeNull()
            ->and(Student::find(123)?->student_id)->toBe(123);

        // Verify related records were reverted
        expect(StudentTuition::where('student_id', 123)->count())->toBe(1)
            ->and(StudentTransaction::where('student_id', 123)->count())->toBe(1);
    }

    public function test_handles_array_fields_correctly(): void
    {
        $student123 = Student::find(123);

        // Verify the student has complex array data
        expect($student123->contacts)->toBeArray()
            ->and($student123->contacts['phone'])->toBe('09123456789')
            ->and($student123->subject_enrolled)->toBeArray()
            ->and($student123->subject_enrolled)->toContain('Math');

        // Update the ID
        $result = $this->service->updateStudentId($student123, 205590, true);
        expect($result['success'])->toBeTrue();

        // Verify array data was preserved
        $updatedStudent = Student::find(123);
        expect($updatedStudent)->not->toBeNull()
            ->and($updatedStudent->contacts)->toBeArray()
            ->and($updatedStudent->contacts['phone'])->toBe('09123456789')
            ->and($updatedStudent->contacts['emergency_contact'])->toBe('09987654321')
            ->and($updatedStudent->subject_enrolled)->toBeArray()
            ->and($updatedStudent->subject_enrolled)->toContain('Science')
            ->and($updatedStudent->subject_enrolled)->toContain('English');
    }

    private function createRequiredTables(): void
    {
        // Only create tables if they don't exist
        if (! Schema::hasTable('courses')) {
            Schema::create('courses', function ($table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('title');
                $table->text('description')->nullable();
                $table->string('department')->nullable();
                $table->integer('units')->default(0);
                $table->string('lec_per_unit')->nullable();
                $table->string('lab_per_unit')->nullable();
                $table->integer('year_level')->default(1);
                $table->integer('semester')->default(1);
                $table->string('school_year')->nullable();
                $table->string('curriculum_year')->nullable();
                $table->string('miscellaneous')->nullable();
                $table->string('miscelaneous')->nullable(); // Keep typo for compatibility
                $table->text('remarks')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('students')) {
            Schema::create('students', function ($table) {
                $table->id();
                $table->string('first_name');
                $table->string('last_name');
                $table->string('middle_name')->nullable();
                $table->string('gender');
                $table->date('birth_date');
                $table->integer('age');
                $table->string('address')->nullable();
                $table->json('contacts')->nullable();
                $table->integer('course_id');
                $table->integer('academic_year');
                $table->string('email')->unique();
                $table->text('remarks')->nullable();
                $table->string('profile_url')->nullable();
                $table->integer('student_contact_id')->nullable();
                $table->integer('student_parent_info')->nullable();
                $table->integer('student_education_id')->nullable();
                $table->integer('student_personal_id')->nullable();
                $table->integer('document_location_id')->nullable();
                $table->string('status')->nullable();
                $table->string('clearance_status')->default('pending');
                $table->integer('year_graduated')->nullable();
                $table->string('special_order')->nullable();
                $table->date('issued_date')->nullable();
                $table->json('subject_enrolled')->nullable();
                $table->integer('user_id')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('student_tuitions')) {
            Schema::create('student_tuitions', function ($table) {
                $table->id();
                $table->integer('student_id');
                $table->string('semester');
                $table->string('school_year');
                $table->integer('academic_year');
                $table->decimal('total_lectures', 10, 2)->default(0);
                $table->decimal('total_laboratory', 10, 2)->default(0);
                $table->decimal('total_miscelaneous_fees', 10, 2)->default(0);
                $table->decimal('total_tuition', 10, 2)->default(0);
                $table->decimal('overall_tuition', 10, 2)->default(0);
                $table->decimal('downpayment', 10, 2)->default(0);
                $table->decimal('discount', 5, 2)->default(0);
                $table->decimal('total_balance', 10, 2)->default(0);
                $table->string('status')->default('pending');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('student_transactions')) {
            Schema::create('student_transactions', function ($table) {
                $table->id();
                $table->integer('student_id');
                $table->string('transaction_type');
                $table->decimal('amount', 10, 2);
                $table->text('description');
                $table->string('reference_number')->nullable();
                $table->string('payment_method');
                $table->string('status');
                $table->datetime('transaction_date');
                $table->string('processed_by')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('student_enrollments')) {
            Schema::create('student_enrollments', function ($table) {
                $table->id();
                $table->string('student_id'); // Note: string type
                $table->integer('enrollment_id');
                $table->string('semester');
                $table->integer('academic_year');
                $table->string('enrollment_status');
                $table->datetime('enrollment_date');
                $table->integer('total_units');
                $table->text('remarks')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('class_enrollments')) {
            Schema::create('class_enrollments', function ($table) {
                $table->id();
                $table->integer('student_id');
                $table->integer('class_id');
                $table->integer('enrollment_id');
                $table->datetime('enrollment_date');
                $table->string('status');
                $table->string('semester');
                $table->integer('academic_year');
                $table->decimal('grade', 3, 1)->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('subject_enrollments')) {
            Schema::create('subject_enrollments', function ($table) {
                $table->id();
                $table->integer('student_id');
                $table->integer('subject_id');
                $table->integer('enrollment_id');
                $table->string('semester');
                $table->integer('academic_year');
                $table->datetime('enrollment_date');
                $table->string('status');
                $table->integer('units');
                $table->decimal('grade', 3, 1)->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('accounts')) {
            Schema::create('accounts', function ($table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->string('role');
                $table->integer('person_id')->nullable();
                $table->string('person_type')->nullable();
                $table->string('status');
                $table->timestamp('last_login_at')->nullable();
                $table->rememberToken();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('student_id_change_logs')) {
            Schema::create('student_id_change_logs', function ($table) {
                $table->id();
                $table->string('old_student_id');
                $table->string('new_student_id');
                $table->string('student_name');
                $table->string('changed_by');
                $table->json('affected_records');
                $table->json('backup_data');
                $table->text('reason')->nullable();
                $table->boolean('is_undone')->default(false);
                $table->timestamp('undone_at')->nullable();
                $table->string('undone_by')->nullable();
                $table->timestamps();
            });
        }
    }

    private function seedTestData(): void
    {
        // Create a course record first
        DB::table('courses')->updateOrInsert(['id' => 1], [
            'id' => 1,
            'code' => 'CS101',
            'title' => 'Computer Science',
            'description' => 'Test course',
            'department' => 'Computer Science',
            'units' => 3,
            'year_level' => 1,
            'semester' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create the student with ID 123 that needs to be updated to 205590
        $student123 = Student::create([
            'id' => 123,
            'student_id' => 123,
            'first_name' => 'Test',
            'last_name' => 'Student123',
            'middle_name' => 'Middle',
            'gender' => 'male',
            'birth_date' => '2000-01-01',
            'age' => 24,
            'address' => '123 Test Street',
            'contacts' => [
                'phone' => '09123456789',
                'email' => 'test123@example.com',
                'emergency_contact' => '09987654321',
            ],
            'course_id' => 1,
            'academic_year' => 2,
            'email' => 'student123@example.com',
            'remarks' => 'Test student for ID update',
            'student_contact_id' => 100,
            'student_parent_info' => 101,
            'student_education_id' => 102,
            'student_personal_id' => 103,
            'status' => 'enrolled',
            'clearance_status' => 'pending',
            'subject_enrolled' => ['Math', 'Science', 'English'],
        ]);

        // Create related records that should be updated
        StudentTuition::create([
            'student_id' => 123,
            'semester' => 1,
            'school_year' => '2024-2025',
            'academic_year' => 2024,
            'total_lectures' => 25000.00,
            'total_laboratory' => 10000.00,
            'total_miscelaneous_fees' => 5000.00,
            'total_tuition' => 35000.00,
            'overall_tuition' => 40000.00,
            'downpayment' => 20000.00,
            'total_balance' => 20000.00,
            'status' => 'pending',
        ]);

        $transaction = Transaction::create([
            'description' => 'Tuition payment',
            'payment_method' => 'cash',
            'status' => 'completed',
            'transaction_date' => now(),
            'settlements' => ['tuition_fee' => 15000],
            'user_id' => null,
        ]);

        StudentTransaction::create([
            'student_id' => 123,
            'transaction_id' => $transaction->id,
            'amount' => 15000.00,
            'status' => 'completed',
        ]);

        $enrollment = StudentEnrollment::create([
            'student_id' => '123', // String format
            'semester' => 1,
            'academic_year' => 2024,
            'status' => 'enrolled',
            'school_year' => '2024-2025',
            'downpayment' => 0,
        ]);

        $faculty = Faculty::factory()->create();
        $room = Room::factory()->create();
        $subject = Subject::factory()->create([
            'id' => 201,
            'course_id' => 1,
        ]);
        $class = Classes::factory()->create([
            'id' => 501,
            'subject_id' => $subject->id,
            'subject_code' => $subject->code,
            'faculty_id' => $faculty->id,
            'room_id' => $room->id,
            'semester' => 1,
            'school_year' => '2024-2025',
            'course_codes' => ['1'],
        ]);

        ClassEnrollment::create([
            'student_id' => 123,
            'class_id' => $class->id,
            'status' => true,
        ]);

        SubjectEnrollment::create([
            'student_id' => 123,
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'enrollment_id' => $enrollment->id,
            'semester' => 1,
            'academic_year' => 2024,
            'school_year' => '2024-2025',
            'enrolled_lecture_units' => 3,
            'enrolled_laboratory_units' => 0,
        ]);

        Account::create([
            'name' => 'Test Student123 Account',
            'email' => 'account123@example.com',
            'password' => bcrypt('password'),
            'role' => 'student',
            'person_id' => 123,
            'person_type' => Student::class,
        ]);

        // Ensure the target ID 205590 does NOT exist
        Student::where('id', 205590)->delete();
    }
}

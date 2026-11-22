<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Features\Toggles\StudentTuition as StudentTuitionFeature;
use App\Filament\Resources\Classes\RelationManagers\ClassPostsRelationManager;
use App\Models\Course;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTuition;
use App\Models\StudentTuitionUpdateRequest;
use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Schema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Pennant\Feature;

it('restricts ClassPostsRelationManager attachment uploads to private disk with MIME and size limits', function (): void {
    $manager = new ClassPostsRelationManager;
    $schema = $manager->form(Schema::make());

    $upload = collect($schema->getComponents())
        ->first(fn (mixed $component): bool => $component instanceof FileUpload && $component->getName() === 'attachments');

    expect($upload)->toBeInstanceOf(FileUpload::class)
        ->and($upload->getDiskName())->toBe((string) config('filesystems.private_disk', 'private'))
        ->and($upload->getVisibility())->toBe('private')
        ->and($upload->getMaxSize())->toBe(10240)
        ->and($upload->getAcceptedFileTypes())->toContain('application/pdf')
        ->and($upload->getAcceptedFileTypes())->not->toContain('text/html')
        ->and($upload->getAcceptedFileTypes())->not->toContain('image/svg+xml');
});

it('stores student tuition receipts privately and blocks unauthorized users from viewing them', function (): void {
    $privateDisk = (string) config('filesystems.private_disk', 'private');
    Storage::fake($privateDisk);
    Storage::fake('public');

    $ownerStudent = Student::factory()->create();
    $ownerUser = User::factory()->create([
        'role' => UserRole::Student,
        'email' => $ownerStudent->email,
    ]);
    $ownerStudent->forceFill(['user_id' => $ownerUser->id])->save();

    $otherStudent = Student::factory()->create();
    $otherUser = User::factory()->create([
        'role' => UserRole::Student,
        'email' => $otherStudent->email,
    ]);
    $otherStudent->forceFill(['user_id' => $otherUser->id])->save();

    Feature::activateForEveryone(StudentTuitionFeature::class);

    $course = Course::factory()->create();
    $ownerStudent->forceFill(['course_id' => $course->id])->save();
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $ownerStudent->id,
        'course_id' => $course->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'academic_year' => 1,
    ]);

    StudentTuition::query()->create([
        'student_id' => $ownerStudent->id,
        'enrollment_id' => $enrollment->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'academic_year' => 1,
        'total_tuition' => 10000,
        'total_lectures' => 10000,
        'total_laboratory' => 0,
        'total_miscelaneous_fees' => 0,
        'overall_tuition' => 10000,
        'total_balance' => 10000,
        'paid' => 0,
        'discount' => 0,
        'downpayment' => 0,
        'status' => 'pending',
    ]);

    $this->actingAs($ownerUser)
        ->post(route('student.tuition.update-requests.store'), [
            'school_year' => '2026 - 2027',
            'semester' => 1,
            'concern_type' => 'missing_payment',
            'amount' => 1500.00,
            'receipt_number' => 'OR-PRIV-559',
            'receipt_image' => UploadedFile::fake()->image('private_receipt.png'),
            'details' => 'Private receipt upload security test.',
        ])
        ->assertRedirect();

    $receipt = StudentTuitionUpdateRequest::query()->with('receipts')->sole()->receipts->sole();

    Storage::disk($privateDisk)->assertExists((string) $receipt->image_path);
    Storage::disk('public')->assertMissing((string) $receipt->image_path);
    expect($receipt->image_url)->toBe(route('tuition-update-requests.receipts.show', $receipt));

    // Unauthenticated guest is redirected to login
    auth()->logout();
    $this->get(route('tuition-update-requests.receipts.show', $receipt))
        ->assertRedirect();

    // Another student cannot read the receipt
    $this->actingAs($otherUser)
        ->get(route('tuition-update-requests.receipts.show', $receipt))
        ->assertForbidden();

    // Owner can view their own receipt
    $this->actingAs($ownerUser)
        ->get(route('tuition-update-requests.receipts.show', $receipt))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('stores student signatures on the private disk and blocks guests and non-admin users', function (): void {
    $privateDisk = (string) config('filesystems.private_disk', 'private');
    Storage::fake($privateDisk);
    Storage::fake('public');

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $studentUser = User::factory()->create(['role' => UserRole::Student]);
    $student = Student::factory()->create();

    $this->actingAs($admin)
        ->post(route('administrators.students.signature.update', $student), [
            'signature' => UploadedFile::fake()->image('sig.png', 400, 120),
        ])
        ->assertRedirect();

    $student->refresh();

    Storage::disk($privateDisk)->assertExists((string) $student->signature_path);
    Storage::disk('public')->assertMissing((string) $student->signature_path);

    $url = route('administrators.students.signature.show', $student);

    auth()->logout();
    $this->get($url)->assertRedirect();

    $this->actingAs($studentUser)->get($url)->assertForbidden();

    $this->actingAs($admin)
        ->get($url)
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

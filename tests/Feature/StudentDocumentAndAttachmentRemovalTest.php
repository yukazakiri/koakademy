<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\DocumentLocation;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

it('removes or empties a fixed document attachment on a student record', function (): void {
    Storage::fake('public');
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $file = UploadedFile::fake()->create('birth_cert.pdf', 200, 'application/pdf');
    $path = $file->store('students/1/documents', 'public');

    $docLocation = DocumentLocation::create([
        'birth_certificate' => $path,
    ]);

    $student = Student::factory()->create([
        'document_location_id' => $docLocation->id,
    ]);

    Storage::disk('public')->assertExists($path);
    expect($student->fresh()->DocumentLocation->birth_certificate)->toBe($path);

    actingAs($admin)
        ->delete(route('administrators.students.documents.fixed.destroy', [
            'student' => $student->id,
            'documentType' => 'birth_certificate',
        ]))
        ->assertRedirect();

    Storage::disk('public')->assertMissing($path);
    expect($student->fresh()->DocumentLocation->birth_certificate)->toBeNull();
});

it('removes or empties a profile photo and clears student profile url', function (): void {
    Storage::fake('public');
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $file = UploadedFile::fake()->image('profile_pic.jpg');
    $path = $file->store('students/2/documents', 'public');

    $docLocation = DocumentLocation::create([
        'picture_1x1' => $path,
    ]);

    $student = Student::factory()->create([
        'document_location_id' => $docLocation->id,
        'profile_url' => $path,
    ]);

    Storage::disk('public')->assertExists($path);

    actingAs($admin)
        ->delete(route('administrators.students.documents.fixed.destroy', [
            'student' => $student->id,
            'documentType' => 'picture_1x1',
        ]))
        ->assertRedirect();

    Storage::disk('public')->assertMissing($path);
    expect($student->fresh()->DocumentLocation->picture_1x1)->toBeNull()
        ->and($student->fresh()->profile_url)->toBeNull();
});

it('aborts with 404 when attempting to remove an invalid document type', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $student = Student::factory()->create();

    actingAs($admin)
        ->delete(route('administrators.students.documents.fixed.destroy', [
            'student' => $student->id,
            'documentType' => 'non_existent_doc_type',
        ]))
        ->assertNotFound();
});

it('removes or empties the student signature from the record', function (): void {
    Storage::fake(config('filesystems.default'));
    $disk = config('filesystems.default');
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $file = UploadedFile::fake()->image('signature.png');
    $path = $file->store('students/3/signatures', $disk);

    $student = Student::factory()->create([
        'signature_path' => $path,
    ]);

    Storage::disk($disk)->assertExists($path);
    expect($student->fresh()->signature_path)->toBe($path);

    actingAs($admin)
        ->delete(route('administrators.students.signature.destroy', $student->id))
        ->assertRedirect();

    Storage::disk($disk)->assertMissing($path);
    expect($student->fresh()->signature_path)->toBeNull();
});

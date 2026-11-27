<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Student;
use App\Models\User;
use App\Services\ClassRosterManagementService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

it('retains a shared banner when an archived class still references it', function (): void {
    Storage::fake('public');
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $path = 'class-banners/shared.png';
    Storage::disk('public')->put($path, 'shared image');
    $settings = array_merge(Classes::getDefaultSettings(), ['banner_image' => $path]);
    $class = Classes::factory()->create(['settings' => $settings]);
    $other = Classes::factory()->create(['settings' => $settings]);
    $other->delete();

    $this->patch(route('administrators.classes.update', $class), ['remove_banner_image' => true])
        ->assertRedirect(route('administrators.classes.index'));

    expect($class->fresh()->getSetting('banner_image'))->toBeNull();
    Storage::disk('public')->assertExists($path);
});

it('keeps a committed replacement if deletion of the old banner fails', function (): void {
    Storage::fake('public');
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $disk = Storage::disk('public');
    $oldPath = 'class-banners/original.png';
    $disk->put($oldPath, 'old image');
    $class = Classes::factory()->create([
        'settings' => array_merge(Classes::getDefaultSettings(), ['banner_image' => $oldPath]),
    ]);
    $failingDisk = Mockery::mock($disk)->makePartial();
    $failingDisk->shouldReceive('delete')->once()->with($oldPath)->andThrow(new RuntimeException('storage unavailable'));
    Storage::set('public', $failingDisk);

    $this->patch(route('administrators.classes.update', $class), [
        'settings' => ['banner_image' => UploadedFile::fake()->image('new.png')],
    ])->assertRedirect(route('administrators.classes.index'));

    $newPath = $class->fresh()->getSetting('banner_image');
    expect($newPath)->not->toBe($oldPath);
    $disk->assertExists($newPath);
});

it('rechecks the persisted grade lock before editing a stale enrollment', function (): void {
    $class = Classes::factory()->create();
    $student = Student::factory()->create();
    $enrollment = ClassEnrollment::factory()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'prelim_grade' => 2.0,
        'is_grades_verified' => false,
        'is_grades_finalized' => false,
    ]);
    $stale = $enrollment->fresh();
    $enrollment->update(['is_grades_verified' => true]);

    expect(fn () => app(ClassRosterManagementService::class)->updateEnrollment($class, $stale, ['prelim_grade' => 1.0]))
        ->toThrow(ValidationException::class);
    expect((float) $enrollment->fresh()->prelim_grade)->toBe(2.0);
});

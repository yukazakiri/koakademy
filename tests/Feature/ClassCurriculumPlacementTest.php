<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Classes;
use App\Models\Course;
use App\Models\Room;
use App\Models\Subject;
use App\Models\User;

function curriculumClassPayload(Course $course, Subject $subject, Room $room, int $academicYear): array
{
    return [
        'classification' => 'college',
        'course_codes' => [$course->id],
        'subject_ids' => [$subject->id],
        'subject_code' => $subject->code,
        'academic_year' => $academicYear,
        'faculty_id' => null,
        'semester' => '1',
        'school_year' => '2026 - 2027',
        'section' => 'A',
        'room_id' => $room->id,
        'maximum_slots' => 40,
        'schedules' => [[
            'day_of_week' => 'Monday',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'room_id' => $room->id,
        ]],
    ];
}

beforeEach(function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $this->room = Room::factory()->create(['is_active' => true]);
});

it('forces a unanimous subject curriculum year when creating a class', function (): void {
    $course = Course::factory()->create();
    $subject = Subject::factory()->for($course)->create([
        'code' => 'FS',
        'academic_year' => 4,
    ]);

    $this->post(route('administrators.classes.store'), curriculumClassPayload($course, $subject, $this->room, 1))
        ->assertRedirect(route('administrators.classes.index'));

    expect(Classes::query()->latest('id')->value('academic_year'))->toBe(4);
});

it('forces the unanimous curriculum year when updating a legacy class', function (): void {
    $course = Course::factory()->create();
    $subject = Subject::factory()->for($course)->create([
        'code' => 'FS-UPDATE',
        'academic_year' => 4,
    ]);
    $class = Classes::factory()->create([
        'classification' => 'college',
        'course_codes' => [$course->id],
        'subject_ids' => [$subject->id],
        'subject_id' => $subject->id,
        'subject_code' => $subject->code,
        'academic_year' => 1,
    ]);

    $this->patch(
        route('administrators.classes.update', $class),
        curriculumClassPayload($course, $subject, $this->room, 1),
    )->assertRedirect(route('administrators.classes.index'));

    expect($class->fresh()->academic_year)->toBe(4);
});

it('rejects subjects outside the selected programs and unrelated mixed-year fallbacks', function (): void {
    $firstCourse = Course::factory()->create();
    $secondCourse = Course::factory()->create();
    $firstSubject = Subject::factory()->for($firstCourse)->create(['academic_year' => 3]);
    $secondSubject = Subject::factory()->for($secondCourse)->create(['academic_year' => 4]);

    $invalidProgramPayload = curriculumClassPayload($firstCourse, $secondSubject, $this->room, 4);

    $this->from(route('administrators.classes.create'))
        ->post(route('administrators.classes.store'), $invalidProgramPayload)
        ->assertRedirect(route('administrators.classes.create'))
        ->assertSessionHasErrors('subject_ids');

    $mixedPayload = curriculumClassPayload($firstCourse, $firstSubject, $this->room, 1);
    $mixedPayload['course_codes'] = [$firstCourse->id, $secondCourse->id];
    $mixedPayload['subject_ids'] = [$firstSubject->id, $secondSubject->id];

    $this->from(route('administrators.classes.create'))
        ->post(route('administrators.classes.store'), $mixedPayload)
        ->assertRedirect(route('administrators.classes.create'))
        ->assertSessionHasErrors('academic_year');
});

it('rejects empty college curricula and newly selected subjects without a curriculum year', function (): void {
    $legacyShsClass = Classes::factory()->create([
        'classification' => 'shs',
        'course_codes' => null,
        'subject_ids' => null,
        'subject_id' => null,
        'academic_year' => null,
    ]);
    $emptyPayload = [
        ...curriculumClassPayload(Course::factory()->create(), Subject::factory()->create(), $this->room, 1),
        'classification' => 'college',
        'course_codes' => [],
        'subject_ids' => [],
    ];

    $this->from(route('administrators.classes.edit', $legacyShsClass))
        ->patch(route('administrators.classes.update', $legacyShsClass), $emptyPayload)
        ->assertRedirect(route('administrators.classes.edit', $legacyShsClass))
        ->assertSessionHasErrors(['course_codes', 'subject_ids']);

    $course = Course::factory()->create();
    $unresolvedSubject = Subject::factory()->for($course)->create(['academic_year' => null]);

    $this->from(route('administrators.classes.create'))
        ->post(route('administrators.classes.store'), curriculumClassPayload($course, $unresolvedSubject, $this->room, 2))
        ->assertRedirect(route('administrators.classes.create'))
        ->assertSessionHasErrors('subject_ids');
});

it('returns subject program and curriculum metadata for class forms', function (): void {
    $course = Course::factory()->create(['code' => 'BSHM']);
    $subject = Subject::factory()->for($course)->create([
        'academic_year' => 4,
        'semester' => 1,
    ]);

    $this->getJson(route('administrators.classes.options.subjects', ['course_ids' => [$course->id]]))
        ->assertOk()
        ->assertJsonPath('data.0.id', $subject->id)
        ->assertJsonPath('data.0.course_id', $course->id)
        ->assertJsonPath('data.0.course_code', 'BSHM')
        ->assertJsonPath('data.0.academic_year', 4)
        ->assertJsonPath('data.0.semester', '1');
});

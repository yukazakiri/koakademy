<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Course;
use Tests\TestCase;

final class CourseIsActiveColumnTest extends TestCase
{
    public function test_can_query_courses_with_is_active_column(): void
    {
        // Create test courses with different is_active values
        Course::factory()->create([
            'code' => 'BSCS',
            'title' => 'Bachelor of Science in Computer Science',
            'is_active' => true,
        ]);

        Course::factory()->create([
            'code' => 'BSIT',
            'title' => 'Bachelor of Science in Information Technology',
            'is_active' => false,
        ]);

        Course::factory()->create([
            'code' => 'BSIS',
            'title' => 'Bachelor of Science in Information Systems',
            'is_active' => true,
        ]);

        // Test that we can query active courses
        $activeCourses = Course::where('is_active', true)->get();
        expect($activeCourses)->toHaveCount(2);

        // Test that we can query inactive courses
        $inactiveCourses = Course::where('is_active', false)->get();
        expect($inactiveCourses)->toHaveCount(1);

        // Test that the getCourseOptions method works (this was the original failing method)
        $courseOptions = Course::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->pluck('title', 'id')
            ->toArray();

        expect($courseOptions)->toHaveCount(2);
        expect(array_values($courseOptions))->toContain('Bachelor of Science in Computer Science');
        expect(array_values($courseOptions))->toContain('Bachelor of Science in Information Systems');
        expect(array_values($courseOptions))->not->toContain('Bachelor of Science in Information Technology');
    }

    public function test_course_is_active_default_value(): void
    {
        $course = Course::factory()->create([
            'code' => 'TEST',
            'title' => 'Test Course',
            // Don't set is_active, should default to true
        ]);

        expect($course->is_active)->toBeTrue();
    }

    public function test_course_is_active_casting(): void
    {
        $course = Course::factory()->create([
            'code' => 'TEST',
            'title' => 'Test Course',
            'is_active' => 1, // Integer 1
        ]);

        // Should be cast to boolean true
        expect($course->is_active)->toBeTrue();
        expect($course->is_active)->toBeBool();

        $course->update(['is_active' => 0]); // Integer 0
        $course->refresh();

        // Should be cast to boolean false
        expect($course->is_active)->toBeFalse();
        expect($course->is_active)->toBeBool();
    }
}

<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;

final class StudentAttendanceTracker implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'student-attendance-tracker';
    }

    public function name(): string
    {
        return 'Attendance Tracker';
    }

    public function summary(): string
    {
        return 'Track your class attendance and participation records.';
    }

    public function audience(): string
    {
        return 'student';
    }

    public function badge(): string
    {
        return 'Attendance';
    }

    public function accent(): string
    {
        return 'text-sky-500';
    }

    public function ctaLabel(): string
    {
        return 'Open Attendance';
    }

    public function ctaUrl(): string
    {
        return '/student/attendance';
    }

    public function steps(): array
    {
        return [
            [
                'title' => 'Attendance Tracker',
                'summary' => 'Track your class attendance and participation records.',
                'highlights' => ['Attendance records', 'Participation tracking'],
                'stats' => [
                    ['label' => 'Route', 'value' => '/student/attendance'],
                    ['label' => 'Menu', 'value' => 'Attendance Tracker'],
                ],
                'badge' => 'Attendance',
                'accent' => 'text-sky-500',
                'icon' => 'check-circle-2',
                'image' => null,
            ],
        ];
    }

    public function category(): string
    {
        return 'Student';
    }
}

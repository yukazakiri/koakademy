<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClassEnrollment;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class TimetableConflictService
{
    /**
     * Detect all types of conflicts for a given schedule or set of schedules
     */
    public function detectConflicts(Collection $schedules): array
    {
        try {
            Log::info('Starting conflict detection', [
                'schedule_count' => $schedules->count(),
            ]);

            $conflicts = [
                'time_room_conflicts' => $this->detectTimeRoomConflicts($schedules),
                'faculty_conflicts' => $this->detectFacultyConflicts($schedules),
                'student_conflicts' => $this->detectStudentConflicts($schedules),
            ];

            $totalConflicts = count($conflicts['time_room_conflicts']) +
                              count($conflicts['faculty_conflicts']) +
                              count($conflicts['student_conflicts']);

            Log::info('Conflict detection completed', [
                'total_conflicts' => $totalConflicts,
                'time_room_conflicts' => count($conflicts['time_room_conflicts']),
                'faculty_conflicts' => count($conflicts['faculty_conflicts']),
                'student_conflicts' => count($conflicts['student_conflicts']),
            ]);

            return $conflicts;
        } catch (Exception $exception) {
            Log::error('Error detecting conflicts', [
                'schedule_count' => $schedules->count(),
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }
    }

    /**
     * Detect time and room conflicts (same time slot and room)
     */
    public function detectTimeRoomConflicts(Collection $schedules): array
    {
        $conflicts = [];
        $grouped = $schedules->groupBy(fn ($schedule): string => $schedule->day_of_week.'_'.$schedule->room_id);

        foreach ($grouped as $dayRoom => $dayRoomSchedules) {
            if ($dayRoomSchedules->count() > 1) {
                $timeConflicts = $this->findTimeOverlaps($dayRoomSchedules);
                if ($timeConflicts !== []) {
                    $conflicts[] = [
                        'type' => 'time_room',
                        'day_room' => $dayRoom,
                        'conflicts' => $timeConflicts,
                        'severity' => 'high',
                    ];
                }
            }
        }

        return $conflicts;
    }

    /**
     * Detect faculty conflicts (same faculty teaching multiple classes at same time)
     */
    public function detectFacultyConflicts(Collection $schedules): array
    {
        $conflicts = [];
        $facultySchedules = $schedules->filter(fn ($schedule): bool => $schedule->class && $schedule->class->faculty_id)->groupBy(fn ($schedule): string => $schedule->day_of_week.'_'.$schedule->class->faculty_id);

        foreach ($facultySchedules as $dayFaculty => $dayFacultySchedules) {
            if ($dayFacultySchedules->count() > 1) {
                $timeConflicts = $this->findTimeOverlaps($dayFacultySchedules);
                if ($timeConflicts !== []) {
                    $conflicts[] = [
                        'type' => 'faculty',
                        'day_faculty' => $dayFaculty,
                        'conflicts' => $timeConflicts,
                        'severity' => 'high',
                    ];
                }
            }
        }

        return $conflicts;
    }

    /**
     * Detect student conflicts (students enrolled in multiple classes at same time)
     */
    public function detectStudentConflicts(Collection $schedules): array
    {
        $conflicts = [];

        // Get all students enrolled in these classes
        $classIds = $schedules->pluck('class_id')->filter()->unique();
        $studentEnrollments = ClassEnrollment::query()->whereIn('class_id', $classIds)
            ->with(['student', 'class'])
            ->get()
            ->groupBy('student_id');

        foreach ($studentEnrollments as $studentId => $enrollments) {
            $studentSchedules = collect();

            foreach ($enrollments as $enrollment) {
                $classSchedules = $schedules->where('class_id', $enrollment->class_id);
                $studentSchedules = $studentSchedules->merge($classSchedules);
            }

            if ($studentSchedules->count() > 1) {
                $dayGroups = $studentSchedules->groupBy('day_of_week');

                foreach ($dayGroups as $day => $daySchedules) {
                    if ($daySchedules->count() > 1) {
                        $timeConflicts = $this->findTimeOverlaps($daySchedules);
                        if ($timeConflicts !== []) {
                            $conflicts[] = [
                                'type' => 'student',
                                'student_id' => $studentId,
                                'day' => $day,
                                'conflicts' => $timeConflicts,
                                'severity' => 'medium',
                            ];
                        }
                    }
                }
            }
        }

        return $conflicts;
    }

    /**
     * Get conflict summary for display
     */
    public function getConflictSummary(array $conflicts): array
    {
        $summary = [
            'total_conflicts' => 0,
            'high_severity' => 0,
            'medium_severity' => 0,
            'low_severity' => 0,
            'by_type' => [
                'time_room' => 0,
                'faculty' => 0,
                'student' => 0,
            ],
        ];

        foreach ($conflicts as $type => $typeConflicts) {
            $summary['by_type'][$type] = count($typeConflicts);
            $summary['total_conflicts'] += count($typeConflicts);

            foreach ($typeConflicts as $typeConflict) {
                switch ($typeConflict['severity']) {
                    case 'high':
                        $summary['high_severity']++;
                        break;
                    case 'medium':
                        $summary['medium_severity']++;
                        break;
                    case 'low':
                        $summary['low_severity']++;
                        break;
                }
            }
        }

        return $summary;
    }

    /**
     * Get color coding for different schedule types
     */
    public function getScheduleColorCode($schedule): array
    {
        $colors = [
            'default' => [
                'bg' => '#f3f4f6',
                'border' => '#d1d5db',
                'text' => '#374151',
                'hover' => '#e5e7eb',
                'class' => 'schedule-default',
            ],
            'college' => [
                'bg' => '#dbeafe',
                'border' => '#bfdbfe',
                'text' => '#1e40af',
                'hover' => '#bfdbfe',
                'class' => 'schedule-college',
            ],
            'shs' => [
                'bg' => '#dcfce7',
                'border' => '#bbf7d0',
                'text' => '#166534',
                'hover' => '#bbf7d0',
                'class' => 'schedule-shs',
            ],
            'conflict' => [
                'bg' => '#fef2f2',
                'border' => '#fca5a5',
                'text' => '#dc2626',
                'hover' => '#fee2e2',
                'class' => 'schedule-conflict',
            ],
        ];

        // Determine color based on classification
        if ($schedule->class) {
            $classification = $schedule->class->classification ?: 'college';

            return $colors[$classification] ?? $colors['default'];
        }

        return $colors['default'];
    }

    /**
     * Get CSS class for schedule entry
     */
    public function getScheduleCssClass($schedule, $hasConflict = false): string
    {
        if ($hasConflict) {
            return 'schedule-conflict';
        }

        if ($schedule->class) {
            $classification = $schedule->class->classification ?: 'college';

            return match ($classification) {
                'shs' => 'schedule-shs',
                'college' => 'schedule-college',
                default => 'schedule-default'
            };
        }

        return 'schedule-default';
    }

    /**
     * Cache conflicts for performance
     */
    public function getCachedConflicts(string $cacheKey, Collection $schedules): array
    {
        try {
            Log::info('Getting cached conflicts', [
                'cache_key' => $cacheKey,
                'schedule_count' => $schedules->count(),
            ]);

            return Cache::remember($cacheKey, 300, fn (): array => $this->detectConflicts($schedules));
        } catch (Exception $exception) {
            Log::error('Error getting cached conflicts', [
                'cache_key' => $cacheKey,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }
    }

    /**
     * Clear conflict cache
     */
    public function clearConflictCache(?string $cacheKey = null): void
    {
        try {
            if (! in_array($cacheKey, [null, '', '0'], true)) {
                Cache::forget($cacheKey);
                Log::info('Conflict cache cleared', ['cache_key' => $cacheKey]);
            } else {
                Cache::flush(); // Clear all cache - use with caution
                Log::warning('All conflict cache flushed');
            }
        } catch (Exception $exception) {
            Log::error('Error clearing conflict cache', [
                'cache_key' => $cacheKey,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }
    }

    /**
     * Find time overlaps between schedules
     */
    private function findTimeOverlaps(Collection $schedules): array
    {
        $conflicts = [];
        $scheduleArray = $schedules->toArray();
        $counter = count($scheduleArray);

        for ($i = 0; $i < $counter; $i++) {
            for ($j = $i + 1; $j < count($scheduleArray); $j++) {
                $schedule1 = $scheduleArray[$i];
                $schedule2 = $scheduleArray[$j];

                if ($this->hasTimeOverlap($schedule1, $schedule2)) {
                    $conflicts[] = [
                        'schedule1' => $schedule1,
                        'schedule2' => $schedule2,
                        'overlap_details' => $this->getOverlapDetails($schedule1, $schedule2),
                    ];
                }
            }
        }

        return $conflicts;
    }

    /**
     * Check if two schedules have time overlap
     */
    private function hasTimeOverlap(array $schedule1, array $schedule2): bool
    {
        $start1 = Carbon::parse($schedule1['start_time']);
        $end1 = Carbon::parse($schedule1['end_time']);
        $start2 = Carbon::parse($schedule2['start_time']);
        $end2 = Carbon::parse($schedule2['end_time']);

        return $start1->lt($end2) && $start2->lt($end1);
    }

    /**
     * Get detailed overlap information
     */
    private function getOverlapDetails(array $schedule1, array $schedule2): array
    {
        $start1 = Carbon::parse($schedule1['start_time']);
        $end1 = Carbon::parse($schedule1['end_time']);
        $start2 = Carbon::parse($schedule2['start_time']);
        $end2 = Carbon::parse($schedule2['end_time']);

        $overlapStart = $start1->gt($start2) ? $start1 : $start2;
        $overlapEnd = $end1->lt($end2) ? $end1 : $end2;

        return [
            'overlap_start' => $overlapStart->format('H:i'),
            'overlap_end' => $overlapEnd->format('H:i'),
            'overlap_duration' => $overlapStart->diffInMinutes($overlapEnd),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Room;
use App\Models\Schedule;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

final class TimetableConflictResolutionService
{
    /**
     * Get suggestions to resolve a specific conflict
     */
    public function getConflictResolutionSuggestions(array $conflict): array
    {
        try {
            $suggestions = [];
            $conflictType = $conflict['type'] ?? 'unknown';

            Log::info('Getting conflict resolution suggestions', [
                'conflict_type' => $conflictType,
            ]);

            $result = match ($conflictType) {
                'time_room' => $this->getRoomTimeConflictSuggestions($conflict),
                'faculty' => $this->getFacultyConflictSuggestions(),
                'student' => $this->getStudentConflictSuggestions(),
                default => $suggestions,
            };

            Log::info('Conflict resolution suggestions generated', [
                'conflict_type' => $conflictType,
                'suggestion_count' => count($result),
            ]);

            return $result;
        } catch (Exception $exception) {
            Log::error('Error getting conflict resolution suggestions', [
                'conflict_type' => $conflict['type'] ?? 'unknown',
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return [];
        }
    }

    /**
     * Apply a resolution suggestion
     */
    public function applyResolution(array $suggestion): bool
    {
        try {
            Log::info('Applying conflict resolution', [
                'resolution_type' => $suggestion['type'] ?? 'unknown',
                'suggestion' => $suggestion,
            ]);

            $result = match ($suggestion['type']) {
                'alternative_room' => $this->applyRoomChange(),
                'alternative_time' => $this->applyTimeChange(),
                'split_class' => $this->applySplitClass(),
                default => false,
            };

            Log::info('Conflict resolution applied', [
                'resolution_type' => $suggestion['type'] ?? 'unknown',
                'success' => $result,
            ]);

            return $result;
        } catch (Exception $exception) {
            Log::error('Failed to apply conflict resolution', [
                'resolution_type' => $suggestion['type'] ?? 'unknown',
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Get suggestions for room/time conflicts
     */
    private function getRoomTimeConflictSuggestions(array $conflict): array
    {
        $suggestions = [];

        if (empty($conflict['conflicts'])) {
            return $suggestions;
        }

        $firstConflict = $conflict['conflicts'][0];
        $schedule1 = $firstConflict['schedule1'];

        // Suggest alternative rooms
        $alternativeRooms = $this->findAlternativeRooms($schedule1);
        if ($alternativeRooms !== []) {
            $suggestions[] = [
                'type' => 'alternative_room',
                'title' => 'Use Alternative Room',
                'description' => 'Move one of the classes to an available room',
                'options' => $alternativeRooms,
                'priority' => 'high',
            ];
        }

        // Suggest alternative time slots
        $alternativeTimeSlots = $this->findAlternativeTimeSlots($schedule1);
        if ($alternativeTimeSlots !== []) {
            $suggestions[] = [
                'type' => 'alternative_time',
                'title' => 'Reschedule to Different Time',
                'description' => 'Move one of the classes to an available time slot',
                'options' => $alternativeTimeSlots,
                'priority' => 'medium',
            ];
        }

        // Suggest splitting the class
        $suggestions[] = [
            'type' => 'split_class',
            'title' => 'Split Class Session',
            'description' => 'Divide the class into multiple shorter sessions',
            'options' => $this->getSplitClassOptions($schedule1),
            'priority' => 'low',
        ];

        return $suggestions;
    }

    /**
     * Get suggestions for faculty conflicts
     */
    private function getFacultyConflictSuggestions(): array
    {
        return [
            // Suggest alternative faculty
            [
                'type' => 'alternative_faculty',
                'title' => 'Assign Alternative Faculty',
                'description' => 'Assign a different qualified faculty member',
                'options' => $this->findAlternativeFaculty(),
                'priority' => 'high',
            ],
            // Suggest rescheduling
            [
                'type' => 'reschedule_faculty',
                'title' => 'Reschedule Classes',
                'description' => 'Move one of the classes to when faculty is available',
                'options' => $this->findFacultyAvailableSlots(),
                'priority' => 'medium',
            ],
        ];
    }

    /**
     * Get suggestions for student conflicts
     */
    private function getStudentConflictSuggestions(): array
    {
        return [
            // Suggest alternative sections
            [
                'type' => 'alternative_section',
                'title' => 'Move to Different Section',
                'description' => 'Transfer students to non-conflicting sections',
                'options' => $this->findAlternativeSections(),
                'priority' => 'medium',
            ],
            // Suggest class rescheduling
            [
                'type' => 'reschedule_class',
                'title' => 'Reschedule One Class',
                'description' => 'Move one of the conflicting classes to a different time',
                'options' => $this->findNonConflictingTimeSlots(),
                'priority' => 'high',
            ],
        ];
    }

    /**
     * Find alternative rooms for a schedule
     */
    private function findAlternativeRooms(array $schedule): array
    {
        $currentRoomId = $schedule['room_id'];
        $dayOfWeek = $schedule['day_of_week'];
        $startTime = $schedule['start_time'];
        $endTime = $schedule['end_time'];

        // Find rooms that are available at the same time
        $availableRooms = Room::query()->whereNotIn('id', [$currentRoomId])
            ->whereDoesntHave('schedules', function ($query) use ($dayOfWeek, $startTime, $endTime): void {
                $query->where('day_of_week', $dayOfWeek)
                    ->where(function ($q) use ($startTime, $endTime): void {
                        $q->whereBetween('start_time', [$startTime, $endTime])
                            ->orWhereBetween('end_time', [$startTime, $endTime])
                            ->orWhere(function ($subQ) use ($startTime, $endTime): void {
                                $subQ->where('start_time', '<=', $startTime)
                                    ->where('end_time', '>=', $endTime);
                            });
                    });
            })
            ->get();

        return $availableRooms->map(fn ($room): array => [
            'id' => $room->id,
            'name' => $room->name,
            'capacity' => $room->capacity ?? 'N/A',
            'type' => $room->type ?? 'Standard',
        ])->toArray();
    }

    /**
     * Find alternative time slots for a schedule
     */
    private function findAlternativeTimeSlots(array $schedule): array
    {
        $roomId = $schedule['room_id'];
        $dayOfWeek = $schedule['day_of_week'];
        $duration = Carbon::parse($schedule['end_time'])->diffInMinutes(Carbon::parse($schedule['start_time']));

        // Define possible time slots (you can make this configurable)
        $possibleSlots = [
            '07:00:00', '08:00:00', '09:00:00', '10:00:00', '11:00:00',
            '13:00:00', '14:00:00', '15:00:00', '16:00:00', '17:00:00',
            '18:00:00', '19:00:00',
        ];

        $availableSlots = [];

        foreach ($possibleSlots as $possibleSlot) {
            $endTime = Carbon::parse($possibleSlot)->addMinutes($duration)->format('H:i:s');

            // Check if this slot is available
            $isAvailable = ! Schedule::query()->where('room_id', $roomId)
                ->where('day_of_week', $dayOfWeek)
                ->where(function ($query) use ($possibleSlot, $endTime): void {
                    $query->whereBetween('start_time', [$possibleSlot, $endTime])
                        ->orWhereBetween('end_time', [$possibleSlot, $endTime])
                        ->orWhere(function ($q) use ($possibleSlot, $endTime): void {
                            $q->where('start_time', '<=', $possibleSlot)
                                ->where('end_time', '>=', $endTime);
                        });
                })
                ->exists();

            if ($isAvailable) {
                $availableSlots[] = [
                    'start_time' => $possibleSlot,
                    'end_time' => $endTime,
                    'formatted' => Carbon::parse($possibleSlot)->format('g:i A').' - '.Carbon::parse($endTime)->format('g:i A'),
                ];
            }
        }

        return $availableSlots;
    }

    /**
     * Get split class options
     */
    private function getSplitClassOptions(array $schedule): array
    {
        $duration = Carbon::parse($schedule['end_time'])->diffInMinutes(Carbon::parse($schedule['start_time']));

        $options = [];

        if ($duration >= 120) { // 2 hours or more
            $options[] = [
                'type' => 'split_half',
                'description' => 'Split into two 1-hour sessions',
                'sessions' => 2,
                'duration_each' => 60,
            ];
        }

        if ($duration >= 180) { // 3 hours or more
            $options[] = [
                'type' => 'split_third',
                'description' => 'Split into three 1-hour sessions',
                'sessions' => 3,
                'duration_each' => 60,
            ];
        }

        return $options;
    }

    /**
     * Find alternative faculty for a conflict
     */
    private function findAlternativeFaculty(): array
    {
        // This would need to be implemented based on your faculty qualification system
        return [
            [
                'id' => 'placeholder',
                'name' => 'Alternative faculty suggestions would be implemented here',
                'qualifications' => 'Based on subject expertise',
            ],
        ];
    }

    /**
     * Find faculty available slots
     */
    private function findFacultyAvailableSlots(): array
    {
        // This would analyze faculty schedules to find available time slots
        return [
            [
                'day' => 'Monday',
                'time' => '2:00 PM - 3:00 PM',
                'available' => true,
            ],
        ];
    }

    /**
     * Find alternative sections
     */
    private function findAlternativeSections(): array
    {
        // This would find other sections of the same subject with available slots
        return [];
    }

    /**
     * Find non-conflicting time slots
     */
    private function findNonConflictingTimeSlots(): array
    {
        // This would analyze all student schedules to find non-conflicting times
        return [];
    }

    /**
     * Apply room change resolution
     */
    private function applyRoomChange(): bool
    {
        // Implementation would update the schedule with new room
        return true;
    }

    /**
     * Apply time change resolution
     */
    private function applyTimeChange(): bool
    {
        // Implementation would update the schedule with new time
        return true;
    }

    /**
     * Apply split class resolution
     */
    private function applySplitClass(): bool
    {
        // Implementation would create multiple schedule entries
        return true;
    }
}

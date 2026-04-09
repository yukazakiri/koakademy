<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Faculty;
use App\Models\StrandSubject;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

final class FacultyClassShareService
{
    /**
     * Get faculty classes for sidebar collapsible menu.
     *
     * @return array<int, array{id: int, subject_code: string, subject_title: string, section: string, classification: string, students_count: int, accent_color: string}>
     */
    public function getFacultyClasses(?User $user): array
    {
        if (! $user || ! $user->isFaculty()) {
            return [];
        }

        $faculty = $this->getFacultyForUser($user);

        if (! $faculty instanceof Faculty) {
            return [];
        }

        $classes = $faculty->classes()
            ->currentAcademicPeriod()
            ->select(['id', 'subject_code', 'section', 'classification', 'faculty_id', 'subject_id', 'shs_strand_id', 'settings', 'room_id'])
            ->withCount('class_enrollments')
            ->get();

        if ($classes->isEmpty()) {
            return [];
        }

        $subjectData = $this->loadSubjectData($classes);

        return $classes->map(function ($class) use ($subjectData): array {
            $subject = $this->findSubject($class, $subjectData);

            $settings = $class->settings ?? [];
            $accentColor = $settings['accent_color'] ?? '#3b82f6';

            return [
                'id' => $class->id,
                'subject_code' => $subject?->code ?? $class->subject_code ?? 'N/A',
                'subject_title' => $subject?->title ?? 'N/A',
                'section' => $class->section ?? 'N/A',
                'classification' => $class->classification ?? 'college',
                'students_count' => $class->class_enrollments_count ?? 0,
                'accent_color' => $accentColor,
            ];
        })->values()->all();
    }

    private function getFacultyForUser(User $user): ?Faculty
    {
        return Cache::remember("faculty_for_user_{$user->email}", 60, fn () => Faculty::where('email', $user->email)->first());
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Class_>  $classes
     * @return array{subjects: \Illuminate\Support\Collection<int|string, Subject>, shsSubjects: \Illuminate\Support\Collection<int|string, StrandSubject>}
     */
    private function loadSubjectData($classes): array
    {
        $subjectCodes = $classes->whereNull('subject_id')->whereNotNull('subject_code')->pluck('subject_code')->unique()->filter();
        $subjectIds = $classes->whereNotNull('subject_id')->pluck('subject_id')->unique()->filter();

        $subjects = collect();
        $shsSubjects = collect();

        if ($subjectCodes->isNotEmpty() || $subjectIds->isNotEmpty()) {
            $subjects = Subject::whereIn('code', $subjectCodes)
                ->orWhereIn('id', $subjectIds)
                ->get()
                ->keyBy('code');
        }

        $shsClass = $classes->firstWhere('classification', 'shs');
        if ($shsClass || $classes->where('classification', 'shs')->isNotEmpty()) {
            $shsSubjectCodes = $classes->where('classification', 'shs')->pluck('subject_code')->unique()->filter();
            if ($shsSubjectCodes->isNotEmpty()) {
                $shsSubjects = StrandSubject::whereIn('code', $shsSubjectCodes)
                    ->get()
                    ->keyBy('code');
            }
        }

        return [
            'subjects' => $subjects,
            'shsSubjects' => $shsSubjects,
        ];
    }

    private function findSubject($class, array $subjectData): mixed
    {
        $subjects = $subjectData['subjects'];
        $shsSubjects = $subjectData['shsSubjects'];

        $subject = null;

        if ($class->subject_id && $subjects->has($class->subject_id)) {
            $subject = $subjects->firstWhere('id', $class->subject_id);
        }

        if (! $subject && $class->subject_code) {
            if ($class->classification === 'shs') {
                $subject = $shsSubjects->get($class->subject_code);
            } else {
                $subject = $subjects->get($class->subject_code);
            }
        }

        return $subject;
    }
}

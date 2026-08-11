<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Classes;
use App\Models\Course;
use App\Models\Schedule;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final readonly class CourseScheduleExportService
{
    /** @var array<string, int> */
    private const array DAY_ORDER = [
        'Monday' => 1,
        'Tuesday' => 2,
        'Wednesday' => 3,
        'Thursday' => 4,
        'Friday' => 5,
        'Saturday' => 6,
        'Sunday' => 7,
    ];

    /** @var array<string, string> */
    private const array DAY_ABBREVIATIONS = [
        'Monday' => 'M',
        'Tuesday' => 'T',
        'Wednesday' => 'W',
        'Thursday' => 'TH',
        'Friday' => 'F',
        'Saturday' => 'SAT',
        'Sunday' => 'SUN',
    ];

    public function __construct(
        private GeneralSettingsService $generalSettings,
        private SchoolBrandingService $branding,
        private ClassCurriculumPlacementService $curriculumPlacement,
    ) {}

    /**
     * @return array{
     *     school: array{name: string, address: string, logo: string, email: string, phone: string},
     *     course: array{id: int, code: string, title: string},
     *     school_year: string,
     *     semester: int,
     *     semester_label: string,
     *     year_groups: list<array{year: string, label: string, rows: list<array{code: string, title: string, section: string, units: int|null, day: string, time: string, room: string}>}>
     * }
     */
    public function build(Course $course): array
    {
        $courseId = (int) $course->getKey();

        $classes = Classes::query()
            ->currentAcademicPeriod()
            ->college()
            ->where(function (Builder $query) use ($courseId): void {
                $query->whereJsonContains('course_codes', $courseId)
                    ->orWhereJsonContains('course_codes', (string) $courseId);
            })
            ->whereHas('Schedule')
            ->with([
                'Subject:id,code,title,units,academic_year,course_id',
                'subjects:id,code,title,units,academic_year,course_id',
                'Schedule' => fn ($query) => $query
                    ->select(['id', 'class_id', 'day_of_week', 'start_time', 'end_time', 'room_id'])
                    ->with('room:id,name'),
            ])
            ->get([
                'id',
                'subject_id',
                'subject_code',
                'subject_ids',
                'academic_year',
                'section',
                'course_codes',
                'classification',
            ]);

        $fallbackSubjects = Subject::query()
            ->where('course_id', $courseId)
            ->whereIn('code', $classes->pluck('subject_code')->filter()->unique())
            ->get(['id', 'code', 'title', 'units', 'academic_year', 'course_id'])
            ->keyBy('code');

        $rows = $classes->flatMap(function (Classes $class) use ($courseId, $fallbackSubjects): array {
            $subjects = $this->curriculumPlacement->subjectsForCourse($class, $courseId);

            if ($subjects->isEmpty() && filled($class->subject_code)) {
                $legacySubject = $fallbackSubjects->get($class->subject_code);

                if ($legacySubject instanceof Subject) {
                    $subjects = collect([$legacySubject]);
                }
            }

            if ($subjects->isEmpty()) {
                return $this->rowsForClass($class, null, $this->resolvedYear(null, $class));
            }

            return $subjects
                ->flatMap(fn (Subject $subject): array => $this->rowsForClass(
                    $class,
                    $subject,
                    $this->resolvedYear($subject, $class),
                ))
                ->all();
        });

        $yearGroups = $rows
            ->groupBy('_year')
            ->map(function (Collection $yearRows, int|string $year): array {
                $normalizedYear = (string) $year;

                return [
                    'year' => $normalizedYear,
                    'label' => $this->yearLabel($normalizedYear),
                    'rows' => $this->rowsForYear($yearRows),
                ];
            })
            ->sort(fn (array $first, array $second): int => $this->yearSortValue($first['year']) <=> $this->yearSortValue($second['year']))
            ->values()
            ->all();

        $branding = $this->branding->resolve();
        $semester = $this->generalSettings->getCurrentSemester();

        return [
            'school' => [
                'name' => $branding['name'],
                'address' => $branding['address'],
                'logo' => $this->printableLogo($branding['logo_embedded'] ?: $branding['logo']),
                'email' => $branding['email'],
                'phone' => $branding['phone'],
            ],
            'course' => [
                'id' => $courseId,
                'code' => (string) $course->code,
                'title' => (string) $course->title,
            ],
            'school_year' => $this->generalSettings->getCurrentSchoolYearString(),
            'semester' => $semester,
            'semester_label' => $this->generalSettings->getAvailableSemesters()[$semester] ?? "Semester {$semester}",
            'year_groups' => $yearGroups,
        ];
    }

    public function filename(Course $course): string
    {
        return sprintf(
            'course-schedule-%s-%s-semester-%d.pdf',
            Str::slug((string) $course->code),
            Str::slug($this->generalSettings->getCurrentSchoolYearString()),
            $this->generalSettings->getCurrentSemester(),
        );
    }

    /**
     * @param  Collection<int, array{code: string, title: string, section: string, units: int|null, day: string, time: string, room: string, _year: string, _day_order: int, _start_minutes: int}>  $rows
     * @return list<array{code: string, title: string, section: string, units: int|null, day: string, time: string, room: string}>
     */
    private function rowsForYear(Collection $rows): array
    {
        return $rows
            ->sort(function (array $first, array $second): int {
                return strnatcasecmp($first['code'], $second['code'])
                    ?: strnatcasecmp($first['section'], $second['section'])
                    ?: $first['_day_order'] <=> $second['_day_order']
                    ?: $first['_start_minutes'] <=> $second['_start_minutes'];
            })
            ->map(function (array $row): array {
                unset($row['_year'], $row['_day_order'], $row['_start_minutes']);

                return $row;
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{code: string, title: string, section: string, units: int|null, day: string, time: string, room: string, _year: string, _day_order: int, _start_minutes: int}>
     */
    private function rowsForClass(Classes $class, ?Subject $subject, string $year): array
    {
        return $class->Schedule
            ->groupBy(fn (Schedule $schedule): string => implode('|', [
                $schedule->start_time->format('H:i'),
                $schedule->end_time->format('H:i'),
                (string) ($schedule->room_id ?? 'none'),
            ]))
            ->map(function (Collection $meetings) use ($class, $subject, $year): array {
                /** @var Schedule $firstMeeting */
                $firstMeeting = $meetings->first();
                $days = $meetings
                    ->pluck('day_of_week')
                    ->filter()
                    ->unique()
                    ->sortBy(fn (string $day): int => self::DAY_ORDER[$day] ?? 99)
                    ->values();

                return [
                    'code' => $this->subjectCode($class, $subject),
                    'title' => $this->subjectTitle($class, $subject),
                    'section' => $class->section ?: '—',
                    'units' => $subject instanceof Subject ? $subject->units : null,
                    'day' => $days->map(fn (string $day): string => self::DAY_ABBREVIATIONS[$day] ?? Str::upper($day))->implode(''),
                    'time' => $firstMeeting->start_time->format('g:i A').' – '.$firstMeeting->end_time->format('g:i A'),
                    'room' => $firstMeeting->room?->name ?: '—',
                    '_year' => $year,
                    '_day_order' => $days->map(fn (string $day): int => self::DAY_ORDER[$day] ?? 99)->min() ?? 99,
                    '_start_minutes' => ((int) $firstMeeting->start_time->format('H') * 60) + (int) $firstMeeting->start_time->format('i'),
                ];
            })
            ->values()
            ->all();
    }

    private function resolvedYear(?Subject $subject, Classes $class): string
    {
        $subjectYear = (int) ($subject?->academic_year ?? 0);

        if ($subjectYear > 0) {
            return (string) $subjectYear;
        }

        return filled($class->academic_year) ? (string) $class->academic_year : 'unknown';
    }

    private function subjectCode(Classes $class, mixed $subject): string
    {
        if ($subject instanceof Subject && filled($subject->code)) {
            return (string) $subject->code;
        }

        return filled($class->subject_code) ? (string) $class->subject_code : '—';
    }

    private function subjectTitle(Classes $class, mixed $subject): string
    {
        if ($subject instanceof Subject && filled($subject->title)) {
            return (string) $subject->title;
        }

        return filled($class->subject_code) ? (string) $class->subject_code : '—';
    }

    private function yearLabel(string $year): string
    {
        return match ($year) {
            '1' => 'FIRST YEAR',
            '2' => 'SECOND YEAR',
            '3' => 'THIRD YEAR',
            '4' => 'FOURTH YEAR',
            'unknown', '' => 'YEAR LEVEL NOT SET',
            default => 'YEAR '.$year,
        };
    }

    private function yearSortValue(string $year): int
    {
        return ctype_digit($year) ? (int) $year : PHP_INT_MAX;
    }

    private function printableLogo(string $logo): string
    {
        if (! str_starts_with($logo, '/')) {
            return $logo;
        }

        $publicRoot = realpath(public_path());
        $path = realpath(public_path(mb_ltrim($logo, '/')));

        if (
            $publicRoot === false
            || $path === false
            || ! str_starts_with($path, $publicRoot.DIRECTORY_SEPARATOR)
            || ! is_file($path)
        ) {
            return $logo;
        }

        $mimeType = mime_content_type($path);
        $contents = file_get_contents($path);

        if (! is_string($mimeType) || ! str_starts_with($mimeType, 'image/') || $contents === false) {
            return $logo;
        }

        return 'data:'.$mimeType.';base64,'.base64_encode($contents);
    }
}

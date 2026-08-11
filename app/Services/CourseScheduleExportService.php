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
     *     year_groups: list<array{
     *         year: string,
     *         label: string,
     *         show_section_labels: bool,
     *         section_groups: list<array{
     *             section: string,
     *             label: string,
     *             rows: list<array{code: string, title: string, units: int|null, schedule: string, room: string, face_to_face: string}>
     *         }>
     *     }>
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
                'Subject.course:id,code,title',
                'subjects:id,code,title,units,academic_year,course_id',
                'subjects.course:id,code,title',
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

        $courseSubjects = Subject::query()
            ->where('course_id', $courseId)
            ->get(['id', 'code', 'title', 'units', 'academic_year', 'course_id'])
            ->groupBy(fn (Subject $subject): string => $this->normalizedSubjectCode($subject->code));

        $rows = $classes->flatMap(function (Classes $class) use ($course, $courseSubjects): array {
            $subjects = $this->subjectsForExport($class, $course, $courseSubjects);

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
                    ...$this->sectionGroupsForYear($yearRows),
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
                'title' => Str::squish((string) $course->title),
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
     * @param  Collection<int, array{code: string, title: string, section: string, units: int|null, schedule: string, room: string, face_to_face: string, _year: string, _day_order: int, _start_minutes: int}>  $rows
     * @return array{
     *     show_section_labels: bool,
     *     section_groups: list<array{
     *         section: string,
     *         label: string,
     *         rows: list<array{code: string, title: string, units: int|null, schedule: string, room: string, face_to_face: string}>
     *     }>
     * }
     */
    private function sectionGroupsForYear(Collection $rows): array
    {
        $sectionGroups = $rows
            ->groupBy('section')
            ->map(function (Collection $sectionRows, string $section): array {
                $rows = $sectionRows
                    ->sort(function (array $first, array $second): int {
                        return strnatcasecmp($first['code'], $second['code'])
                            ?: $first['_day_order'] <=> $second['_day_order']
                            ?: $first['_start_minutes'] <=> $second['_start_minutes'];
                    })
                    ->map(function (array $row): array {
                        unset($row['section'], $row['_year'], $row['_day_order'], $row['_start_minutes']);

                        return $row;
                    })
                    ->values()
                    ->all();

                return [
                    'section' => $section,
                    'label' => $section === '—' ? 'SECTION NOT SET' : 'SECTION '.$section,
                    'rows' => $rows,
                ];
            })
            ->sort(fn (array $first, array $second): int => $this->sectionSortValue($first['section']) <=> $this->sectionSortValue($second['section']))
            ->values()
            ->all();

        return [
            'show_section_labels' => count($sectionGroups) > 1,
            'section_groups' => $sectionGroups,
        ];
    }

    /**
     * @return list<array{code: string, title: string, section: string, units: int|null, schedule: string, room: string, face_to_face: string, _year: string, _day_order: int, _start_minutes: int}>
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
                    'schedule' => $firstMeeting->start_time->format('g:i A').' – '.$firstMeeting->end_time->format('g:i A').' '.$days->map(fn (string $day): string => self::DAY_ABBREVIATIONS[$day] ?? Str::upper($day))->implode(''),
                    'room' => $firstMeeting->room?->name ?: '—',
                    'face_to_face' => $days->map(fn (string $day): string => Str::upper($day))->implode(', '),
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
            return Str::squish((string) $subject->code);
        }

        return $this->legacySubjectCodes($class)->first() ?? '—';
    }

    private function subjectTitle(Classes $class, mixed $subject): string
    {
        if ($subject instanceof Subject && filled($subject->title)) {
            return Str::squish((string) $subject->title);
        }

        return '—';
    }

    /**
     * Resolve the selected program's curriculum subject first. Legacy shared classes may only
     * link the equivalent subject from a sibling curriculum, so use it only when its exported
     * metadata is unambiguous across every matching linked subject.
     *
     * @param  Collection<string, Collection<int, Subject>>  $courseSubjects
     * @return Collection<int, Subject>
     */
    private function subjectsForExport(Classes $class, Course $course, Collection $courseSubjects): Collection
    {
        $courseId = (int) $course->getKey();
        $subjects = $this->curriculumPlacement->subjectsForCourse($class, $courseId);

        if ($subjects->isNotEmpty()) {
            return $this->uniqueSubjects($subjects);
        }

        $legacyCodes = $this->legacySubjectCodes($class)
            ->map(fn (string $code): string => $this->normalizedSubjectCode($code))
            ->filter()
            ->unique()
            ->values();

        $courseMatches = $legacyCodes
            ->flatMap(function (string $code) use ($courseSubjects): Collection {
                $matches = $courseSubjects->get($code, collect());

                return $this->unambiguousSubjects($matches);
            });

        if ($courseMatches->isNotEmpty()) {
            return $this->uniqueSubjects($courseMatches);
        }

        $linkedSubjects = $this->curriculumPlacement->subjectsForClass($class);
        $programFamily = $this->programFamily($course);
        $linkedMatches = $linkedSubjects
            ->when(
                $legacyCodes->isNotEmpty(),
                fn (Collection $subjects): Collection => $subjects->filter(
                    fn (Subject $subject): bool => $legacyCodes->contains($this->normalizedSubjectCode($subject->code)),
                ),
            )
            ->groupBy(fn (Subject $subject): string => $this->normalizedSubjectCode($subject->code))
            ->flatMap(function (Collection $matches) use ($programFamily): Collection {
                $familyMatches = $matches->filter(
                    fn (Subject $subject): bool => $subject->course instanceof Course
                        && $this->programFamily($subject->course) === $programFamily,
                );

                return $this->unambiguousSubjects($familyMatches->isNotEmpty() ? $familyMatches : $matches);
            });

        return $this->uniqueSubjects($linkedMatches);
    }

    /**
     * @param  Collection<int, Subject>  $subjects
     * @return Collection<int, Subject>
     */
    private function unambiguousSubjects(Collection $subjects): Collection
    {
        if ($subjects->isEmpty()) {
            return collect();
        }

        $identities = $subjects
            ->map(fn (Subject $subject): string => implode('|', [
                $this->normalizedSubjectCode($subject->code),
                Str::upper(Str::squish((string) $subject->title)),
                (string) ($subject->units ?? ''),
                (string) ($subject->academic_year ?? ''),
            ]))
            ->unique();

        return $identities->count() === 1 ? collect([$subjects->first()]) : collect();
    }

    /**
     * @param  Collection<int, Subject>  $subjects
     * @return Collection<int, Subject>
     */
    private function uniqueSubjects(Collection $subjects): Collection
    {
        return $subjects
            ->filter(fn (mixed $subject): bool => $subject instanceof Subject)
            ->unique(fn (Subject $subject): string => implode('|', [
                $this->normalizedSubjectCode($subject->code),
                Str::upper(Str::squish((string) $subject->title)),
                (string) ($subject->units ?? ''),
                (string) ($subject->academic_year ?? ''),
            ]))
            ->values();
    }

    /** @return Collection<int, string> */
    private function legacySubjectCodes(Classes $class): Collection
    {
        return collect(explode(',', (string) $class->subject_code))
            ->map(fn (string $code): string => Str::squish($code))
            ->filter()
            ->unique(fn (string $code): string => $this->normalizedSubjectCode($code))
            ->values();
    }

    private function normalizedSubjectCode(mixed $code): string
    {
        return Str::upper(Str::squish((string) $code));
    }

    private function programFamily(Course $course): string
    {
        $firstCodeToken = Str::of((string) $course->code)
            ->squish()
            ->before(' ')
            ->upper()
            ->toString();

        return preg_replace('/[^A-Z0-9]/', '', $firstCodeToken) ?: $firstCodeToken;
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

    private function sectionSortValue(string $section): string
    {
        return $section === '—' ? 'ZZZZZZ' : Str::upper($section);
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

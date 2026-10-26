<?php

declare(strict_types=1);

namespace Modules\EnterpriseReporting\Services;

use App\Enterprise\CapabilityAccessService;
use App\Models\School;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final readonly class ConsolidatedEnrollmentReport
{
    public const string CAPABILITY = 'enterprise.consolidated-reports';

    public function __construct(private CapabilityAccessService $access) {}

    /** @return Collection<int, School> */
    public function availableSchools(User $user): Collection
    {
        return School::query()->where('is_active', true)
            ->when(! $user->is_super_admin, fn ($query) => $query->where(function ($query) use ($user): void {
                $query->where('id', $user->school_id)->orWhereIn('id', $user->organizations()->wherePivot('is_active', true)->select('schools.id'));
            }))
            ->orderBy('name')->get()
            ->filter(fn (School $school): bool => $this->access->decide(self::CAPABILITY, $user, $school)->allowed)
            ->values();
    }

    /**
     * @param  list<int>  $schoolIds
     * @return array{rows: list<array{school_id: int, name: string, code: string, enrollments: int, students: int, courses: int}>, totals: array{schools: int, enrollments: int, students: int}}
     */
    public function generate(User $user, array $schoolIds, string $schoolYear, int $semester): array
    {
        $schools = School::query()->whereIn('id', $schoolIds)->where('is_active', true)->orderBy('name')->get();
        abort_unless(count($schoolIds) > 0 && $schools->count() === count(array_unique($schoolIds)), 404);

        foreach ($schools as $school) {
            $decision = $this->access->decide(self::CAPABILITY, $user, $school);
            abort_unless($decision->allowed, 403, 'Every selected school must be accessible, licensed, and enabled for consolidated reporting.');
        }

        // Cross-school reporting removes only the school scope, then applies
        // an explicit, fully authorized school allowlist. Soft deletes remain.
        /** @var \Illuminate\Support\Collection<int, object{school_id: int, enrollments: int|numeric-string, students: int|numeric-string, courses: int|numeric-string}> $counts */
        $counts = StudentEnrollment::withoutSchoolScope()
            ->whereIn('school_id', $schools->modelKeys())
            ->forAcademicPeriod($schoolYear, $semester)
            ->selectRaw('school_id, COUNT(*) AS enrollments, COUNT(DISTINCT student_id) AS students, COUNT(DISTINCT course_id) AS courses')
            ->groupBy('school_id')->toBase()->get()->keyBy('school_id');

        $rows = $schools->map(function (School $school) use ($counts): array {
            $count = $counts->get($school->id);

            return [
                'school_id' => $school->id, 'name' => $school->name, 'code' => $school->code,
                'enrollments' => (int) ($count->enrollments ?? 0),
                'students' => (int) ($count->students ?? 0),
                'courses' => (int) ($count->courses ?? 0),
            ];
        })->values()->all();
        $rows = array_values($rows);

        return ['rows' => $rows, 'totals' => [
            'schools' => count($rows), 'enrollments' => array_sum(array_column($rows, 'enrollments')),
            'students' => array_sum(array_column($rows, 'students')),
        ]];
    }
}

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Collapses duplicate curriculum records before the school-scoped unique keys
 * are built by `2026_11_09_000002_scope_course_and_subject_codes_to_school`.
 *
 * A school stores one `courses` row per curriculum implementation, so the
 * program code is the natural key inside a school. Two rows that share both
 * `school_id` and `code` are the same curriculum recorded twice, which is
 * what the unique index refused to accept:
 *
 *   ERROR: could not create unique index "courses_school_id_code_unique"
 *   DETAIL: Key (school_id, code)=(4, BSHRM (2009 - 2010)) is duplicated.
 *
 * Rather than renaming a program an administrator already recognises, the
 * duplicate is merged into the oldest row that carries the code. Everything
 * that pointed at the duplicate is repointed at the survivor:
 *
 *   - `subject` rows move to the survivor course. A subject whose code
 *     already exists on the survivor is merged into that subject instead of
 *     becoming a second row with the same code.
 *   - `students`, `student_enrollment`, `curriculum_imports` and
 *     `library_research_papers` move to the survivor course.
 *   - `enrollment_policies` move to the survivor course and their
 *     `scope_key` is recomputed, because the key hashes the course id. A
 *     policy whose recomputed key already exists covers the same scope as a
 *     policy that is already on the survivor course. That copy is retired
 *     rather than deleted: it keeps its published versions, it stops
 *     resolving the scope, and the surviving policy is enabled when the
 *     retired one was the enabled one.
 *   - `fee_schedule_versions` move to the survivor course. A version that
 *     collides with an existing version of the same scope describes the
 *     same period, but not necessarily the same rates, so it continues as
 *     the next revision of that scope instead of being deleted.
 *   - `classes.course_codes` and `classes.subject_ids` are JSON arrays of
 *     ids and are rewritten in place.
 *
 * Subjects that share a code inside a single course are merged first, so the
 * per-course subject code stays unique afterwards.
 *
 * The values that only the duplicate row carried cannot all survive the
 * merge: one subject code, one program code and one scope can only be held
 * once. Every such difference is written to the `migration_merge_conflicts`
 * registry before the duplicate row is removed, so an operator can review
 * what the merge kept and what it set aside.
 *
 * The merge is idempotent: a second run finds no duplicate codes and changes
 * nothing. It is not reversible, because the duplicate rows are deleted and
 * their references are repointed; recovery is the database backup taken
 * before the release step.
 */
return new class extends Migration
{
    /**
     * The registry of the values the merge could not carry over.
     *
     * The merge keeps the value that existed first. Every value that only
     * the duplicate row carried is written here, so nothing is lost with the
     * deleted row and an operator can review the differences afterwards.
     */
    private const CONFLICT_TABLE = 'migration_merge_conflicts';

    private const MIGRATION_NAME = '2026_11_08_000001_merge_duplicate_courses';

    /**
     * The curriculum values of a subject that are compared before the
     * duplicate row is removed.
     *
     * @var list<string>
     */
    private const SUBJECT_COMPARED_FIELDS = [
        'title',
        'units',
        'lecture',
        'laboratory',
        'academic_year',
        'semester',
        'classification',
        'is_credited',
        'group',
        'description',
        'name',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('courses')) {
            return;
        }

        $this->ensureConflictRegistry();

        $this->mergeDuplicateSubjectsWithinCourses();
        $this->mergeDuplicateCourses();
        $this->pruneDanglingSubjectReferences();
    }

    /**
     * Reverse the migrations.
     *
     * The merge deletes the duplicate rows and repoints their references at
     * the surviving record. There is no way to tell which references used to
     * belong to which program, so a rollback restores the pre-release
     * database backup instead of this method. The conflict registry is kept
     * for the same reason: it is the only record of the values the merge
     * set aside.
     */
    public function down(): void
    {
        // Intentionally empty. See the note above.
    }

    /**
     * Create the registry that records the values the merge kept.
     */
    private function ensureConflictRegistry(): void
    {
        if (Schema::hasTable(self::CONFLICT_TABLE)) {
            return;
        }

        Schema::create(self::CONFLICT_TABLE, function (Blueprint $table): void {
            $table->id();
            $table->string('migration', 191);
            $table->string('relation', 64);
            $table->unsignedBigInteger('keeper_id')->nullable();
            $table->unsignedBigInteger('duplicate_id')->nullable();
            $table->string('scope', 191)->nullable();
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['migration', 'relation']);
        });
    }

    /**
     * Record a value that the merge could not carry over.
     *
     * @param  array<string, mixed>  $details
     */
    private function recordConflict(string $relation, ?int $keeperId, ?int $duplicateId, ?string $scope, array $details): void
    {
        if (! Schema::hasTable(self::CONFLICT_TABLE)) {
            return;
        }

        DB::table(self::CONFLICT_TABLE)->insert([
            'migration' => self::MIGRATION_NAME,
            'relation' => $relation,
            'keeper_id' => $keeperId,
            'duplicate_id' => $duplicateId,
            'scope' => $scope === null ? null : mb_substr($scope, 0, 191),
            'details' => json_encode($details),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Merge courses that share a school and a program code.
     */
    private function mergeDuplicateCourses(): void
    {
        foreach ($this->duplicateCourseGroups() as $group) {
            $survivor = null;

            foreach ($group as $courseId) {
                if ($survivor === null) {
                    $survivor = $courseId;

                    continue;
                }

                $this->mergeCourseIntoSurvivor($courseId, $survivor);
            }
        }
    }

    /**
     * Courses that would violate a unique key on (school_id, code).
     *
     * The school is resolved the same way the scope migration resolves it:
     * the course's own school, else the school of its department, else the
     * first school in the installation.
     *
     * @return list<list<int>>
     */
    private function duplicateCourseGroups(): array
    {
        $groups = [];

        foreach (DB::table('courses')->orderBy('id')->get() as $course) {
            $schoolId = $this->effectiveSchoolId($course);

            if ($schoolId === null) {
                continue;
            }

            $groups[$schoolId.'|'.$course->code][] = (int) $course->id;
        }

        return array_values(array_filter($groups, fn (array $ids): bool => count($ids) > 1));
    }

    /**
     * The school a course belongs to once the scope migration has backfilled
     * it.
     */
    private function effectiveSchoolId(object $course): ?int
    {
        if ($course->school_id !== null) {
            return (int) $course->school_id;
        }

        if (isset($course->department_id) && $course->department_id !== null && Schema::hasTable('departments')) {
            $departmentSchoolId = DB::table('departments')
                ->where('id', $course->department_id)
                ->value('school_id');

            if ($departmentSchoolId !== null) {
                return (int) $departmentSchoolId;
            }
        }

        return $this->defaultSchoolId();
    }

    private function defaultSchoolId(): ?int
    {
        if (! Schema::hasTable('schools')) {
            return null;
        }

        $schoolId = DB::table('schools')->orderBy('id')->value('id');

        return $schoolId === null ? null : (int) $schoolId;
    }

    /**
     * Move one duplicate course and everything that points at it onto the
     * surviving course.
     */
    private function mergeCourseIntoSurvivor(int $duplicateId, int $survivorId): void
    {
        $survivor = DB::table('courses')->where('id', $survivorId)->first();

        if ($survivor === null) {
            return;
        }

        $schoolId = $this->effectiveSchoolId($survivor);

        $this->mergeCourseSubjects($duplicateId, $survivorId, $schoolId);
        $this->moveCourseReferences($duplicateId, $survivorId);

        DB::table('courses')->where('id', $duplicateId)->delete();
    }

    /**
     * Move the subjects of a duplicate course onto the surviving course.
     *
     * A subject whose code is already used on the survivor is merged into
     * that subject, because two subjects with the same code in one course
     * would break the per-course subject key.
     */
    private function mergeCourseSubjects(int $duplicateId, int $survivorId, ?int $schoolId): void
    {
        if (! Schema::hasTable('subject')) {
            return;
        }

        $subjects = DB::table('subject')->where('course_id', $duplicateId)->orderBy('id')->get();

        foreach ($subjects as $subject) {
            $keeper = DB::table('subject')
                ->where('course_id', $survivorId)
                ->where('code', $subject->code)
                ->orderBy('id')
                ->first();

            if ($keeper === null) {
                $update = ['course_id' => $survivorId];

                if ($schoolId !== null) {
                    $update['school_id'] = $schoolId;
                }

                DB::table('subject')->where('id', $subject->id)->update($update);

                continue;
            }

            $this->mergeSubjectRows((int) $subject->id, (int) $keeper->id);
        }
    }

    /**
     * Merge subjects that share a code inside one course.
     */
    private function mergeDuplicateSubjectsWithinCourses(): void
    {
        if (! Schema::hasTable('subject')) {
            return;
        }

        $groups = [];

        foreach (DB::table('subject')->whereNotNull('course_id')->orderBy('id')->get() as $subject) {
            $groups[$subject->course_id.'|'.$subject->code][] = (int) $subject->id;
        }

        foreach ($groups as $ids) {
            if (count($ids) < 2) {
                continue;
            }

            $keeper = array_shift($ids);

            foreach ($ids as $duplicateId) {
                $this->mergeSubjectRows($duplicateId, (int) $keeper);
            }
        }
    }

    /**
     * Fold one subject row into another.
     *
     * Every reference to the duplicate is repointed at the keeper, including
     * the JSON arrays that store subject ids.
     */
    private function mergeSubjectRows(int $duplicateId, int $keeperId): void
    {
        foreach ([
            ['classes', 'subject_id'],
            ['schedule', 'subject_id'],
            ['subject_enrollments', 'subject_id'],
        ] as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::table($table)->where($column, $duplicateId)->update([$column => $keeperId]);
        }

        if (Schema::hasTable('subject_enrollments') && Schema::hasColumn('subject_enrollments', 'credited_subject_id')) {
            DB::table('subject_enrollments')
                ->where('credited_subject_id', $duplicateId)
                ->update(['credited_subject_id' => $keeperId]);
        }

        if (Schema::hasTable('activity_log') && Schema::hasColumn('activity_log', 'subject_id')) {
            DB::table('activity_log')
                ->where('subject_id', $duplicateId)
                ->update(['subject_id' => $keeperId]);
        }

        $this->remapJsonReferences('classes', 'subject_ids', $duplicateId, $keeperId);
        $this->remapJsonReferences('subject', 'pre_riquisite', $duplicateId, $keeperId);

        $this->mergeSubjectPrerequisites($duplicateId, $keeperId);
        $this->recordSubjectConflict($duplicateId, $keeperId);

        DB::table('subject')->where('id', $duplicateId)->delete();
    }

    /**
     * Record the curriculum values the duplicate subject carried.
     *
     * The keeper keeps its own values, because it is the row that existed
     * first. The values that differed are written to the conflict registry,
     * so a value that only the duplicate carried is still recoverable after
     * the merge.
     */
    private function recordSubjectConflict(int $duplicateId, int $keeperId): void
    {
        $keeper = DB::table('subject')->where('id', $keeperId)->first();
        $duplicate = DB::table('subject')->where('id', $duplicateId)->first();

        if ($keeper === null || $duplicate === null) {
            return;
        }

        $differences = [];

        foreach (self::SUBJECT_COMPARED_FIELDS as $field) {
            $keeperValue = $keeper->{$field} ?? null;
            $duplicateValue = $duplicate->{$field} ?? null;

            if ((string) $keeperValue === (string) $duplicateValue) {
                continue;
            }

            $differences[$field] = [
                'keeper' => $keeperValue,
                'duplicate' => $duplicateValue,
            ];
        }

        if ($differences === []) {
            return;
        }

        $this->recordConflict('subject', $keeperId, $duplicateId, $duplicate->code, $differences);
    }

    /**
     * Carry the prerequisites of the duplicate over to the keeper.
     *
     * The two subjects describe the same subject code, so the prerequisite
     * list of the duplicate is part of the same subject and must not be lost
     * with the deleted row.
     */
    private function mergeSubjectPrerequisites(int $duplicateId, int $keeperId): void
    {
        if (! Schema::hasColumn('subject', 'pre_riquisite')) {
            return;
        }

        $prerequisites = $this->decodeIdList(DB::table('subject')->where('id', $duplicateId)->value('pre_riquisite'));

        foreach ($this->decodeIdList(DB::table('subject')->where('id', $keeperId)->value('pre_riquisite')) as $id) {
            $prerequisites[] = $id;
        }

        $prerequisites = array_values(array_unique(array_filter(
            $prerequisites,
            fn (int $id): bool => $id !== $keeperId && $id !== $duplicateId,
        )));

        if ($prerequisites === []) {
            return;
        }

        DB::table('subject')
            ->where('id', $keeperId)
            ->update(['pre_riquisite' => json_encode($prerequisites)]);
    }

    /**
     * The ids held by a JSON array column.
     *
     * @return list<int>
     */
    private function decodeIdList(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return [];
        }

        $ids = [];

        foreach ($decoded as $item) {
            $id = $this->jsonValueToId($item);

            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * Repoint the tables that reference a course.
     */
    private function moveCourseReferences(int $duplicateId, int $survivorId): void
    {
        foreach (['students', 'student_enrollment', 'curriculum_imports', 'library_research_papers'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'course_id')) {
                continue;
            }

            DB::table($table)->where('course_id', $duplicateId)->update(['course_id' => $survivorId]);
        }

        $this->moveEnrollmentPolicies($duplicateId, $survivorId);
        $this->moveFeeScheduleVersions($duplicateId, $survivorId);

        $this->remapJsonReferences('classes', 'course_codes', $duplicateId, $survivorId);
    }

    /**
     * Move course scoped enrollment policies onto the surviving course.
     *
     * The scope key hashes the course id, so it has to be recomputed. Two
     * policies cannot share a scope key, and the survivor course may already
     * carry a policy for the same scope. The copy on the duplicate course is
     * then retired rather than deleted: it keeps its published versions, it
     * stops resolving the scope, and the difference between the two policies
     * is written to the conflict registry. The scope keeps resolving
     * through an enabled policy, so the surviving policy is enabled when the
     * retired one was the enabled one.
     */
    private function moveEnrollmentPolicies(int $duplicateId, int $survivorId): void
    {
        if (! Schema::hasTable('enrollment_policies')) {
            return;
        }

        $policies = DB::table('enrollment_policies')->where('course_id', $duplicateId)->orderBy('id')->get();

        foreach ($policies as $policy) {
            $scopeKey = $this->enrollmentPolicyScopeKey(
                $policy->school_id,
                $policy->student_type,
                $survivorId,
                $policy->school_year,
                $policy->semester,
            );

            $conflict = DB::table('enrollment_policies')
                ->where('scope_key', $scopeKey)
                ->where('id', '!=', $policy->id)
                ->first();

            if ($conflict !== null) {
                $this->retireEnrollmentPolicy($policy, $conflict, $scopeKey);

                continue;
            }

            DB::table('enrollment_policies')
                ->where('id', $policy->id)
                ->update(['course_id' => $survivorId, 'scope_key' => $scopeKey]);
        }
    }

    /**
     * Retire the policy of the duplicate course.
     *
     * The policy keeps its published versions and its history. It stops
     * resolving the scope because it is disabled and because its scope key
     * no longer collides with the policy that keeps the scope.
     */
    private function retireEnrollmentPolicy(object $policy, object $keeper, string $scopeKey): void
    {
        $duplicateEnabled = (bool) ($policy->is_enabled ?? false);
        $keeperEnabled = (bool) ($keeper->is_enabled ?? false);

        // The scope keeps resolving through an enabled policy.
        if ($duplicateEnabled && ! $keeperEnabled) {
            DB::table('enrollment_policies')->where('id', $keeper->id)->update(['is_enabled' => true]);
        }

        $this->recordConflict('enrollment_policy', (int) $keeper->id, (int) $policy->id, $policy->name, [
            'scope_key' => $scopeKey,
            'retired_scope_key' => 'retired:'.$scopeKey,
            'duplicate_is_enabled' => $duplicateEnabled,
            'keeper_is_enabled' => $keeperEnabled,
            'student_type' => $policy->student_type,
            'school_year' => $policy->school_year,
            'semester' => $policy->semester,
        ]);

        DB::table('enrollment_policies')
            ->where('id', $policy->id)
            ->update([
                'is_enabled' => false,
                'course_id' => null,
                'scope_key' => mb_substr('retired:'.$scopeKey, 0, 255),
            ]);
    }

    /**
     * The scope key of an enrollment policy.
     *
     * Mirrors `App\Models\EnrollmentPolicy::scopeKey()` so the recomputed
     * key matches the one the application would create.
     */
    private function enrollmentPolicyScopeKey(
        mixed $schoolId,
        mixed $studentType,
        mixed $courseId,
        mixed $schoolYear,
        mixed $semester,
    ): string {
        return hash('sha256', implode('|', [
            'school:'.($schoolId ?? '*'),
            'student_type:'.($studentType ?? '*'),
            'course:'.($courseId ?? '*'),
            'school_year:'.($schoolYear ?? '*'),
            'semester:'.($semester ?? '*'),
        ]));
    }

    /**
     * Move fee schedule versions onto the surviving course.
     *
     * A version is unique per course, school year, semester, academic year
     * and version number. A version of the duplicate course that collides
     * with one of the survivor describes the same scope, but not necessarily
     * the same schedule: the rates, the fees and the rules can differ. The
     * colliding version is therefore kept as a later revision of the
     * surviving scope instead of being deleted, and the difference between
     * the two schedules is written to the conflict registry.
     */
    private function moveFeeScheduleVersions(int $duplicateId, int $survivorId): void
    {
        if (! Schema::hasTable('fee_schedule_versions')) {
            return;
        }

        $versions = DB::table('fee_schedule_versions')->where('course_id', $duplicateId)->orderBy('id')->get();

        foreach ($versions as $version) {
            if ($version->academic_year !== null) {
                $conflict = DB::table('fee_schedule_versions')
                    ->where('course_id', $survivorId)
                    ->where('school_year', $version->school_year)
                    ->where('semester', $version->semester)
                    ->where('academic_year', $version->academic_year)
                    ->where('version', $version->version)
                    ->first();

                if ($conflict !== null) {
                    $this->renumberFeeScheduleVersion($version, $conflict, $survivorId);

                    continue;
                }
            }

            DB::table('fee_schedule_versions')
                ->where('id', $version->id)
                ->update(['course_id' => $survivorId]);
        }
    }

    /**
     * Keep a colliding fee schedule version as a later revision.
     *
     * The version number is what makes the scope unique, so the colliding
     * version continues as the next revision of the same scope. Its rates,
     * fees and rules stay untouched, and the assessments that reference it
     * keep their reference.
     */
    private function renumberFeeScheduleVersion(object $version, object $conflict, int $survivorId): void
    {
        $query = DB::table('fee_schedule_versions')
            ->where('course_id', $survivorId)
            ->where('school_year', $version->school_year)
            ->where('semester', $version->semester);

        $version->academic_year === null
            ? $query->whereNull('academic_year')
            : $query->where('academic_year', $version->academic_year);

        $nextVersion = max((int) $query->max('version'), (int) $version->version) + 1;

        $this->recordConflict('fee_schedule_version', (int) $conflict->id, (int) $version->id, $version->public_id ?? null, [
            'school_year' => $version->school_year,
            'semester' => $version->semester,
            'academic_year' => $version->academic_year,
            'previous_version' => $version->version,
            'version' => $nextVersion,
            'keeper_rates' => [
                'lecture_rate_per_unit' => $conflict->lecture_rate_per_unit ?? null,
                'laboratory_rate_per_unit' => $conflict->laboratory_rate_per_unit ?? null,
                'miscellaneous_fee' => $conflict->miscellaneous_fee ?? null,
                'modular_fee' => $conflict->modular_fee ?? null,
                'nstp_multiplier' => $conflict->nstp_multiplier ?? null,
                'modular_lab_multiplier' => $conflict->modular_lab_multiplier ?? null,
                'is_active' => $conflict->is_active ?? null,
            ],
            'duplicate_rates' => [
                'lecture_rate_per_unit' => $version->lecture_rate_per_unit ?? null,
                'laboratory_rate_per_unit' => $version->laboratory_rate_per_unit ?? null,
                'miscellaneous_fee' => $version->miscellaneous_fee ?? null,
                'modular_fee' => $version->modular_fee ?? null,
                'nstp_multiplier' => $version->nstp_multiplier ?? null,
                'modular_lab_multiplier' => $version->modular_lab_multiplier ?? null,
                'is_active' => $version->is_active ?? null,
            ],
        ]);

        DB::table('fee_schedule_versions')
            ->where('id', $version->id)
            ->update(['course_id' => $survivorId, 'version' => $nextVersion]);
    }

    /**
     * Remove subject ids that no longer exist from the JSON id lists.
     *
     * A subject that was merged into another one leaves its id behind in
     * the prerequisite lists and class subject lists that were not touched
     * by the merge itself.
     */
    private function pruneDanglingSubjectReferences(): void
    {
        if (! Schema::hasTable('subject')) {
            return;
        }

        $existing = array_flip(array_map(
            'intval',
            DB::table('subject')->pluck('id')->all(),
        ));

        foreach (['subject' => 'pre_riquisite', 'classes' => 'subject_ids'] as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $rows = DB::table($table)->whereNotNull($column)->select('id', $column)->get();

            foreach ($rows as $row) {
                $decoded = json_decode((string) $row->{$column}, true);

                if (! is_array($decoded)) {
                    continue;
                }

                $kept = [];
                $changed = false;

                foreach ($decoded as $value) {
                    $id = $this->jsonValueToId($value);

                    if ($id !== null && ! isset($existing[$id])) {
                        $changed = true;

                        continue;
                    }

                    $kept[] = $value;
                }

                if (! $changed) {
                    continue;
                }

                DB::table($table)
                    ->where('id', $row->id)
                    ->update([$column => json_encode(array_values($kept))]);
            }
        }
    }

    /**
     * Replace one id with another inside a JSON array column.
     *
     * Rows that do not contain the id are left untouched, so a column that
     * holds something other than ids keeps its contents.
     */
    private function remapJsonReferences(string $table, string $column, int $from, int $to): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $rows = DB::table($table)->whereNotNull($column)->select('id', $column)->get();

        foreach ($rows as $row) {
            $decoded = json_decode((string) $row->{$column}, true);

            if (! is_array($decoded)) {
                continue;
            }

            $contains = false;

            foreach ($decoded as $value) {
                if ($this->jsonValueIsId($value, $from)) {
                    $contains = true;

                    break;
                }
            }

            if (! $contains) {
                continue;
            }

            $seen = [];
            $updated = [];

            foreach ($decoded as $value) {
                if ($this->jsonValueIsId($value, $from)) {
                    $value = $to;
                }

                $key = is_scalar($value) ? (string) $value : json_encode($value);

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $updated[] = $value;
            }

            DB::table($table)->where('id', $row->id)->update([$column => json_encode($updated)]);
        }
    }

    /**
     * Whether a decoded JSON value is the given id.
     */
    private function jsonValueIsId(mixed $value, int $id): bool
    {
        return $this->jsonValueToId($value) === $id;
    }

    /**
     * The id a decoded JSON value holds, when it holds one.
     */
    private function jsonValueToId(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return null;
    }
};

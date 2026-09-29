<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enums\StudentStatus;
use App\Enums\StudentType;
use App\Mcp\Concerns\AuthorizesMcpRequests;
use App\Models\Course;
use App\Models\Student;
use App\Models\StudentClearance;
use App\Support\RegistrarStudentProfileWorkbook;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Throwable;

#[Description('Manage student records in the selected school: create new student profiles, update status or program information, archive records, or fetch details.')]
final class ManageStudentTool extends Tool
{
    use AuthorizesMcpRequests;

    /**
     * Special equity and origin fields this tool can read and write.
     *
     * Each key exists in RegistrarStudentProfileWorkbook::PROFILE_FIELDS, which owns the
     * authoritative option lists (CHED disability types, region codes, income brackets).
     * Rules and schema descriptions are derived from there so the two surfaces cannot drift.
     */
    private const array EQUITY_FIELDS = [
        'ethnicity',
        'region_of_origin',
        'province_of_origin',
        'city_of_origin',
        'is_indigenous_person',
        'indigenous_group',
        'is_pwd',
        'pwd_type',
        'is_solo_parent',
        'is_solo_parent_dependent',
        'is_senior_citizen',
        'is_magna_carta',
        'is_underprivileged',
        'is_first_generation',
        'family_income_bracket',
    ];

    /**
     * Equity fields where an explicit null clears the stored value.
     *
     * Booleans are excluded: they are never "cleared", a false simply records "not a member".
     */
    private const array NULLABLE_EQUITY_FIELDS = [
        'ethnicity',
        'region_of_origin',
        'province_of_origin',
        'city_of_origin',
        'indigenous_group',
        'pwd_type',
        'family_income_bracket',
    ];

    public function handle(Request $request): ResponseFactory
    {
        $action = mb_strtolower((string) $request->get('action'));

        if ($action === 'get') {
            $user = $this->requireRead($request);
            $this->requirePermission($user, 'View:Student', 'You are not permitted to view student records.');

            $studentId = $request->get('student_id');
            $student = $this->resolveStudent((string) $studentId);

            if (! $student instanceof Student) {
                return Response::structured(['found' => false, 'message' => "Student '{$studentId}' not found."]);
            }

            return Response::structured([
                'found' => true,
                'id' => $student->id,
                'student_number' => (string) $student->student_id,
                'name' => $student->full_name,
                'email' => $student->email,
                'status' => $student->status,
                'year_level' => $student->academic_year,
                'program' => $student->Course?->code,
                'equity' => $this->equityPayload($student),
            ]);
        }

        $user = $this->requireWrite($request);
        if ($action === 'create') {
            $this->requirePermission($user, 'Create:Student', 'You are not permitted to create student records.');
        } elseif ($action === 'batch_upsert') {
            $this->requirePermission($user, 'Create:Student', 'You are not permitted to create student records.');
            $this->requirePermission($user, 'Update:Student', 'You are not permitted to modify student records.');
        } elseif ($action === 'archive' || $action === 'delete') {
            $this->requirePermission($user, 'Update:Student', 'You are not permitted to archive student records.');
        } else {
            $this->requirePermission($user, 'Update:Student', 'You are not permitted to modify student records.');
        }

        return match ($action) {
            'create' => $this->handleCreate($request),
            'update' => $this->handleUpdate($request),
            'batch_upsert' => $this->handleBatchUpsert($request),
            'archive', 'delete' => $this->handleArchive($request),
            default => Response::structured(['error' => true, 'message' => "Unsupported action '{$action}'. Valid: create, update, batch_upsert, archive, get."]),
        };
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->enum(['create', 'update', 'batch_upsert', 'archive', 'get'])->required()->description('Operation: create, update, batch_upsert, archive, get.'),
            'students' => $schema->array()->description('List of students for batch_upsert.')->items(
                $schema->object(fn ($s) => [
                    'student_id' => $s->string()->description('Student database ID or student number.'),
                    'lrn' => $s->string()->description('12-digit Learner Reference Number.'),
                    'first_name' => $s->string()->description('Student first name.'),
                    'last_name' => $s->string()->description('Student last name.'),
                    'email' => $s->string()->description('Student email address.'),
                    'course_code' => $s->string()->description('Program code (e.g. BSCS, BSHM).'),
                    'academic_year' => $s->integer()->description('Year level 1-5.'),
                    'status' => $s->string()->description('Status: enrolled, applicant, etc.'),
                    'gender' => $s->string()->description('Male, Female, Other.'),
                ])
            ),
            'student_id' => $schema->string()->description('Student database ID or student number.'),
            'first_name' => $schema->string()->description('Student first name.'),
            'last_name' => $schema->string()->description('Student last name.'),
            'email' => $schema->string()->description('Student email address.'),
            'course_code' => $schema->string()->description('Degree program code (e.g. BSCS).'),
            'course_id' => $schema->integer()->description('Degree program database ID.'),
            'academic_year' => $schema->integer()->description('Year level (1-5).'),
            'status' => $schema->string()->description('Status: enrolled, applicant, graduated, on_leave, dropped.'),
            'idempotency_key' => $schema->string()->description('Unique idempotency key for safe retries on create.'),
            'ethnicity' => $schema->string()->description('Ethnicity / ethnolinguistic group, e.g. Ilocano, Kankanaey, Ibaloi.'),
            'region_of_origin' => $this->choiceField($schema, 'region_of_origin', 'Region of origin. Accepts "CAR - Cordillera Administrative Region" style values and normalizes them.'),
            'province_of_origin' => $schema->string()->description('Province of origin.'),
            'city_of_origin' => $schema->string()->description('City / municipality of origin.'),
            'is_indigenous_person' => $schema->boolean()->description('Member of an Indigenous Peoples (IP) community.'),
            'indigenous_group' => $schema->string()->description('Indigenous group name when is_indigenous_person is true, e.g. Kankanaey, Ibaloi, Ifugao.'),
            'is_pwd' => $schema->boolean()->description('Person with Disability (PWD).'),
            'pwd_type' => $this->choiceField($schema, 'pwd_type', 'CHED disability type. Required when is_pwd is true.'),
            'is_solo_parent' => $schema->boolean()->description('Solo parent student.'),
            'is_solo_parent_dependent' => $schema->boolean()->description('Dependent of a solo parent.'),
            'is_senior_citizen' => $schema->boolean()->description('Senior citizen student.'),
            'is_magna_carta' => $schema->boolean()->description('Magna Carta of the Poor beneficiary.'),
            'is_underprivileged' => $schema->boolean()->description('Classified as underprivileged / low-income assistance.'),
            'is_first_generation' => $schema->boolean()->description('First-generation college student.'),
            'family_income_bracket' => $this->choiceField($schema, 'family_income_bracket', 'Annual household income bracket. Accepts the label form e.g. "₱250,000 and below" and normalizes to the stored key.'),
        ];
    }

    /**
     * Build a schema string field whose enum comes from the registrar workbook, so the
     * advertised options always match what the write path will accept.
     */
    private function choiceField(JsonSchema $schema, string $key, string $description): Type
    {
        $field = $this->workbookField($key);
        $options = array_keys($field['options'] ?? []);

        $type = $schema->string();

        if ($options !== []) {
            $type = $type->enum($options);
        }

        return $type->description($description.($options !== [] ? ' One of: '.implode(', ', $options).'.' : ''));
    }

    private function handleCreate(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'course_id' => ['nullable', 'integer'],
            'course_code' => ['nullable', 'string', 'max:50'],
            'academic_year' => ['nullable', 'integer', 'between:1,5'],
            'student_type' => ['nullable', 'string', 'in:college,shs'],
            'gender' => ['nullable', 'string', 'in:Male,Female,Other'],
            'birth_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string'],
            'idempotency_key' => ['nullable', 'string', 'max:96'],
            ...$this->equityRules(),
        ]);

        $equity = $this->normalizeEquity($validated);
        if (isset($equity['__error'])) {
            return $this->equityErrorResponse('create', $equity['__error']);
        }

        $idempotencyKey = $validated['idempotency_key'] ?? null;
        if ($idempotencyKey) {
            $school = $this->school();
            $cacheKey = "mcp:create-student:{$school->id}:{$idempotencyKey}";
            $cachedStudentId = \Illuminate\Support\Facades\Cache::get($cacheKey);

            if ($cachedStudentId) {
                $existingStudent = Student::query()->find($cachedStudentId);
                if ($existingStudent instanceof Student) {
                    return Response::structured([
                        'success' => true,
                        'action' => 'create',
                        'replayed' => true,
                        'idempotency_key' => $idempotencyKey,
                        'student' => [
                            'id' => $existingStudent->id,
                            'student_number' => (string) $existingStudent->student_id,
                            'name' => $existingStudent->full_name,
                            'email' => $existingStudent->email,
                            'status' => $existingStudent->status,
                        ],
                    ]);
                }
            }
        }

        $courseId = $validated['course_id'] ?? null;
        if (! $courseId && filled($validated['course_code'] ?? null)) {
            $course = Course::query()->where('code', $validated['course_code'])->first();
            $courseId = $course?->id;
        }

        if (! $courseId) {
            $defaultCourse = Course::query()->first();
            $courseId = $defaultCourse?->id ?? 1;
        }

        $studentType = isset($validated['student_type']) && $validated['student_type'] === 'shs'
            ? StudentType::Shs
            : StudentType::College;

        $school = $this->school();
        $student = DB::transaction(function () use ($validated, $courseId, $studentType, $school, $idempotencyKey, $equity) {
            $newStudentId = Student::generateNextId($studentType);
            $birthDate = filled($validated['birth_date'] ?? null)
                ? Carbon::parse($validated['birth_date'])
                : now()->subYears(18);

            $newStudent = Student::query()->create([
                'student_id' => $newStudentId,
                'institution_id' => $school->id,
                'school_id' => $school->id,
                'student_type' => $studentType->value,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'email' => $validated['email'],
                'course_id' => $courseId,
                'academic_year' => $validated['academic_year'] ?? 1,
                'gender' => $validated['gender'] ?? 'Other',
                'birth_date' => $birthDate->format('Y-m-d'),
                'age' => $birthDate->age,
                'status' => $validated['status'] ?? StudentStatus::Applicant->value,
                ...$equity,
            ]);

            $generalSetting = \App\Models\GeneralSetting::query()->first();
            if ($generalSetting instanceof \App\Models\GeneralSetting) {
                StudentClearance::createForCurrentSemester($newStudent, $generalSetting);
            }

            if ($idempotencyKey) {
                \Illuminate\Support\Facades\Cache::put("mcp:create-student:{$school->id}:{$idempotencyKey}", $newStudent->id, now()->addDays(7));
            }

            return $newStudent;
        });

        return Response::structured([
            'success' => true,
            'action' => 'create',
            'replayed' => false,
            'idempotency_key' => $idempotencyKey,
            'student' => [
                'id' => $student->id,
                'student_number' => (string) $student->student_id,
                'name' => $student->full_name,
                'email' => $student->email,
                'status' => $student->status,
            ],
            'equity' => $this->equityPayload($student),
        ]);
    }

    private function handleUpdate(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'student_id' => ['required'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            'academic_year' => ['nullable', 'integer', 'between:1,5'],
            'status' => ['nullable', 'string'],
            ...$this->equityRules(),
        ]);

        $student = $this->resolveStudent((string) $validated['student_id']);
        if (! $student instanceof Student) {
            return Response::structured(['error' => true, 'message' => "Student '{$validated['student_id']}' not found."]);
        }

        $equity = $this->normalizeEquity($validated);
        if (isset($equity['__error'])) {
            return $this->equityErrorResponse('update', $equity['__error']);
        }

        $updates = array_filter([
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'email' => $validated['email'] ?? null,
            'academic_year' => $validated['academic_year'] ?? null,
            'status' => $validated['status'] ?? null,
        ], fn ($val) => $val !== null);

        // Equity values already carry their own presence semantics: booleans keep a
        // literal false, and a nullable field sent as null is an intentional clear.
        $updates += $equity;

        $student->update($updates);

        return Response::structured([
            'success' => true,
            'action' => 'update',
            'updated_fields' => array_keys($updates),
            'student' => [
                'id' => $student->id,
                'student_number' => (string) $student->student_id,
                'name' => $student->full_name,
                'status' => $student->status,
            ],
            'equity' => $this->equityPayload($student),
        ]);
    }

    private function handleBatchUpsert(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'students' => ['required', 'array', 'min:1'],
            'students.*.first_name' => ['required', 'string', 'max:100'],
            'students.*.last_name' => ['required', 'string', 'max:100'],
            'students.*.email' => ['required', 'email', 'max:150'],
            'students.*.student_id' => ['nullable'],
            'students.*.lrn' => ['nullable', 'string', 'max:20'],
            'students.*.course_code' => ['nullable', 'string', 'max:50'],
            'students.*.course_id' => ['nullable', 'integer'],
            'students.*.academic_year' => ['nullable', 'integer', 'between:1,5'],
            'students.*.gender' => ['nullable', 'string', 'in:Male,Female,Other'],
            'students.*.status' => ['nullable', 'string'],
        ]);

        $created = [];
        $updated = [];
        $errors = [];

        $generalSetting = \App\Models\GeneralSetting::query()->first();
        $defaultCourse = Course::query()->first();

        DB::transaction(function () use ($validated, &$created, &$updated, &$errors, $generalSetting, $defaultCourse) {
            foreach ($validated['students'] as $idx => $sData) {
                try {
                    $existing = null;
                    if (filled($sData['student_id'] ?? null)) {
                        $existing = $this->resolveStudent((string) $sData['student_id']);
                    }
                    if (! $existing && filled($sData['email'] ?? null)) {
                        $existing = $this->resolveStudent((string) $sData['email']);
                    }
                    if (! $existing && filled($sData['lrn'] ?? null)) {
                        $existing = Student::query()->where('lrn', mb_trim((string) $sData['lrn']))->first();
                    }

                    $courseId = $sData['course_id'] ?? null;
                    if (! $courseId && filled($sData['course_code'] ?? null)) {
                        $c = Course::query()->where('code', $sData['course_code'])->first();
                        $courseId = $c?->id;
                    }
                    if (! $courseId && ! $existing) {
                        $courseId = $defaultCourse?->id ?? 1;
                    }

                    if ($existing instanceof Student) {
                        $updates = array_filter([
                            'first_name' => $sData['first_name'] ?? null,
                            'last_name' => $sData['last_name'] ?? null,
                            'email' => $sData['email'] ?? null,
                            'course_id' => $courseId,
                            'academic_year' => $sData['academic_year'] ?? null,
                            'status' => $sData['status'] ?? null,
                            'gender' => $sData['gender'] ?? null,
                            'lrn' => $sData['lrn'] ?? null,
                        ], fn ($val) => $val !== null);

                        if (! empty($updates)) {
                            $existing->update($updates);
                        }

                        $updated[] = [
                            'id' => $existing->id,
                            'student_number' => (string) $existing->student_id,
                            'name' => $existing->full_name,
                            'email' => $existing->email,
                            'status' => $existing->status,
                        ];
                    } else {
                        $newStudentId = Student::generateNextId(StudentType::College);
                        $newStudent = Student::query()->create([
                            'student_id' => $newStudentId,
                            'institution_id' => 1,
                            'student_type' => StudentType::College->value,
                            'first_name' => $sData['first_name'],
                            'last_name' => $sData['last_name'],
                            'email' => $sData['email'],
                            'course_id' => $courseId,
                            'academic_year' => $sData['academic_year'] ?? 1,
                            'gender' => $sData['gender'] ?? 'Other',
                            'birth_date' => now()->subYears(18)->format('Y-m-d'),
                            'age' => 18,
                            'status' => $sData['status'] ?? StudentStatus::Applicant->value,
                            'lrn' => $sData['lrn'] ?? null,
                        ]);

                        if ($generalSetting instanceof \App\Models\GeneralSetting) {
                            StudentClearance::createForCurrentSemester($newStudent, $generalSetting);
                        }

                        $created[] = [
                            'id' => $newStudent->id,
                            'student_number' => (string) $newStudent->student_id,
                            'name' => $newStudent->full_name,
                            'email' => $newStudent->email,
                            'status' => $newStudent->status,
                        ];
                    }
                } catch (Throwable $rowEx) {
                    $errors[] = 'Row '.($idx + 1)." ({$sData['first_name']} {$sData['last_name']}): ".$rowEx->getMessage();
                }
            }
        });

        return Response::structured([
            'success' => true,
            'action' => 'batch_upsert',
            'created_count' => count($created),
            'updated_count' => count($updated),
            'total_processed' => count($created) + count($updated),
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
        ]);
    }

    private function handleArchive(Request $request): ResponseFactory
    {
        $student = $this->resolveStudent((string) $request->get('student_id'));
        if (! $student instanceof Student) {
            return Response::structured(['error' => true, 'message' => 'Student not found.']);
        }

        $student->update(['status' => StudentStatus::Dropped->value]);

        return Response::structured([
            'success' => true,
            'action' => 'archive',
            'message' => "Student {$student->full_name} marked as dropped.",
        ]);
    }

    private function resolveStudent(string $identifier): ?Student
    {
        if (is_numeric($identifier)) {
            $found = Student::query()->find((int) $identifier);
            if ($found) {
                return $found;
            }
        }

        return Student::query()
            ->where('student_id', $identifier)
            ->orWhere('email', $identifier)
            ->first();
    }

    /** @return array<string, mixed>|null */
    private function workbookField(string $key): ?array
    {
        return app(RegistrarStudentProfileWorkbook::class)->field($key);
    }

    /**
     * Structural validation rules for the equity fields.
     *
     * These deliberately stay lenient about *content* (any string of a sane length) and
     * let normalizeEquity() reject bad values afterwards. That way a caller may send a
     * human label like "₱250,000 and below" and get it canonicalized, rather than being
     * handed a raw rule violation with no hint of the accepted set.
     *
     * @return array<string, list<string>>
     */
    private function equityRules(): array
    {
        $rules = [];

        foreach (self::EQUITY_FIELDS as $key) {
            $field = $this->workbookField($key);
            $max = (int) ($field['max'] ?? 255);

            $rules[$key] = ($field['type'] ?? 'string') === 'boolean'
                ? ['nullable', 'boolean']
                : ['nullable', 'string', 'max:'.$max];
        }

        return $rules;
    }

    /**
     * Canonicalize equity input, mirroring the registrar import's normalization.
     *
     * Returns either the values ready to persist, or ['__error' => [...]] listing the
     * fields that could not be normalized. Only fields the caller actually supplied are
     * returned, so an absent field never overwrites a stored value.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function normalizeEquity(array $validated): array
    {
        $workbook = app(RegistrarStudentProfileWorkbook::class);
        $values = [];
        $errors = [];

        foreach (self::EQUITY_FIELDS as $key) {
            if (! array_key_exists($key, $validated)) {
                continue;
            }

            $raw = $validated[$key];

            // Booleans arrive as real booleans. (string) false is '' in PHP, which the
            // workbook would read as "blank", so they must bypass normalizeInput().
            if (($this->workbookField($key)['type'] ?? null) === 'boolean') {
                $values[$key] = (bool) $raw;

                continue;
            }

            if ($raw === null) {
                if (in_array($key, self::NULLABLE_EQUITY_FIELDS, true)) {
                    $values[$key] = null;
                }

                continue;
            }

            [$normalized, $error] = $workbook->normalizeInput($key, $raw);

            if ($error !== null) {
                $errors[] = ['field' => $key, 'message' => $error];

                continue;
            }

            if ($normalized !== null) {
                $values[$key] = $normalized;
            }
        }

        if ($errors !== []) {
            return ['__error' => $errors];
        }

        return $values;
    }

    /** @param array<int, array{field: string, message: string}> $errors */
    private function equityErrorResponse(string $action, array $errors): ResponseFactory
    {
        return Response::structured([
            'error' => true,
            'action' => $action,
            'message' => 'One or more special equity values are not valid.',
            'invalid_fields' => $errors,
        ]);
    }

    /** @return array<string, mixed> */
    private function equityPayload(Student $student): array
    {
        return [
            'ethnicity' => $student->ethnicity,
            'region_of_origin' => $student->region_of_origin,
            'province_of_origin' => $student->province_of_origin,
            'city_of_origin' => $student->city_of_origin,
            'is_indigenous_person' => (bool) $student->is_indigenous_person,
            'indigenous_group' => $student->indigenous_group,
            'is_pwd' => (bool) $student->is_pwd,
            'pwd_type' => $student->pwd_type,
            'is_solo_parent' => (bool) $student->is_solo_parent,
            'is_solo_parent_dependent' => (bool) $student->is_solo_parent_dependent,
            'is_senior_citizen' => (bool) $student->is_senior_citizen,
            'is_magna_carta' => (bool) $student->is_magna_carta,
            'is_underprivileged' => (bool) $student->is_underprivileged,
            'is_first_generation' => (bool) $student->is_first_generation,
            'family_income_bracket' => $student->family_income_bracket,
        ];
    }
}

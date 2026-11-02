<?php

declare(strict_types=1);

use App\Enums\StudentType;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\IdentifierGenerator;
use App\Services\StudentConfigurationService;
use App\Services\TenantContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    config(['activitylog.enabled' => false]);
    $this->withoutVite();
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::Developer, 'school_id' => $this->school->id]);
    $this->actingAs($this->admin);
    app(TenantContext::class)->setCurrentSchool($this->school);
});

function studentPolicyPayload(Course $course, array $overrides = []): array
{
    return array_replace([
        'student_type' => 'college', 'identifier_assignment' => 'automatic',
        'status' => 'enrolled', 'first_name' => 'Amina', 'last_name' => 'Example',
        'gender' => 'female', 'birth_date' => '2001-02-03', 'academic_year' => 1,
        'course_id' => $course->id,
    ], $overrides);
}

it('uses neutral defaults for new schools without requiring a Philippine identifier', function (): void {
    $config = app(StudentConfigurationService::class)->get();
    expect($config['enabled_types'])->toBe(['college'])
        ->and($config['external_identifier']['enabled'])->toBeFalse()
        ->and($config['defaults']['gender'])->toBe('')
        ->and($config['defaults']['nationality'])->toBe('')
        ->and(app(IdentifierGenerator::class)->previewStudentId())->toBe('000001');
});

it('preserves formatting and consumes counters only when an automatic record is saved', function (): void {
    $generator = app(IdentifierGenerator::class);
    $sequence = $generator->sequenceFor('student');
    $sequence->update(['prefix_mode' => 'static', 'prefix_value' => 'STU-', 'character_set' => 'alphanumeric']);
    expect($generator->previewStudentId())->toBe('STU-000001')
        ->and($generator->previewStudentId())->toBe('STU-000001')
        ->and($sequence->fresh()->next_number)->toBe(1);
    $course = Course::factory()->create();
    $this->post(route('administrators.students.store'), studentPolicyPayload($course, ['student_id' => 'STALE-PREVIEW']))->assertSessionHasNoErrors()->assertRedirect();
    expect(Student::query()->sole()->student_id)->toBe('STU-000001')
        ->and($sequence->fresh()->next_number)->toBe(2);
});

it('isolates configuration and numbering between schools', function (): void {
    $generator = app(IdentifierGenerator::class);
    expect($generator->generateStudentId())->toBe('000001');
    $other = School::factory()->create();
    app(TenantContext::class)->setCurrentSchool($other);
    expect($generator->generateStudentId())->toBe('000001');
    $config = StudentConfigurationService::defaults();
    $config['defaults']['nationality'] = 'Kenyan';
    app(StudentConfigurationService::class)->update($config);
    app(TenantContext::class)->setCurrentSchool($this->school);
    expect($generator->previewStudentId())->toBe('000002')
        ->and(app(StudentConfigurationService::class)->get()['defaults']['nationality'])->toBe('');
});

it('applies required fields and rejects disabled categories on the server', function (): void {
    $config = StudentConfigurationService::defaults();
    $config['fields']['email']['required'] = true;
    app(StudentConfigurationService::class)->update($config);
    $course = Course::factory()->create();
    $this->post(route('administrators.students.store'), studentPolicyPayload($course))->assertSessionHasErrors('email');
    $this->post(route('administrators.students.store'), studentPolicyPayload($course, ['student_type' => 'tesda', 'email' => 'example@example.test']))->assertSessionHasErrors('student_type');
    expect(Student::query()->count())->toBe(0);
});

it('does not allow manual overrides for an automatic-only policy', function (): void {
    app(IdentifierGenerator::class)->sequenceFor('student')->update(['assignment_mode' => 'automatic']);
    $this->post(route('administrators.students.store'), studentPolicyPayload(Course::factory()->create(), ['identifier_assignment' => 'manual', 'student_id' => '000042']))->assertSessionHasErrors('student_id');
});

it('keeps leading zeros for manual identifiers and skips occupied automatic numbers', function (): void {
    $course = Course::factory()->create();
    $this->post(route('administrators.students.store'), studentPolicyPayload($course, ['identifier_assignment' => 'manual', 'student_id' => '000001']))->assertSessionHasNoErrors();
    $this->post(route('administrators.students.store'), studentPolicyPayload($course))->assertSessionHasNoErrors();
    expect(Student::query()->orderBy('id')->pluck('student_id')->all())->toBe(['000001', '000002']);
});

it('validates student settings and protects their update permission', function (): void {
    $config = StudentConfigurationService::defaults();
    $config['fields']['email'] = ['label' => 'School email', 'visible' => false, 'required' => true];
    $this->put(route('administrators.system-management.student-configuration.update'), $config)->assertSessionHasErrors('fields.email.required');
    $user = User::factory()->create(['role' => UserRole::Student, 'school_id' => $this->school->id]);
    $this->actingAs($user)->put(route('administrators.system-management.student-configuration.update'), StudentConfigurationService::defaults())->assertForbidden();
});

it('lets schools add institution fields and keeps archived student values', function (): void {
    $settings = StudentConfigurationService::defaults();
    $settings['custom_fields'] = [
        ['key' => 'custom_1', 'label' => 'Cohort', 'type' => 'choice', 'active' => true, 'required' => true, 'options' => ['Morning', 'Evening']],
        ['key' => 'custom_2', 'label' => 'Locker', 'type' => 'text', 'active' => true, 'required' => false, 'options' => []],
    ];
    $this->put(route('administrators.system-management.student-configuration.update'), $settings)->assertSessionHasNoErrors();
    $course = Course::factory()->create();
    $this->post(route('administrators.students.store'), studentPolicyPayload($course))->assertSessionHasErrors('custom_fields.custom_1');
    $this->post(route('administrators.students.store'), studentPolicyPayload($course, ['custom_fields' => ['custom_1' => null]]))->assertSessionHasErrors('custom_fields.custom_1');
    $this->post(route('administrators.students.store'), studentPolicyPayload($course, ['custom_fields' => ['custom_1' => 'Unknown']]))->assertSessionHasErrors('custom_fields.custom_1');
    $this->post(route('administrators.students.store'), studentPolicyPayload($course, ['custom_fields' => ['custom_1' => 'Evening', 'custom_2' => 'B12', 'unconfigured' => 'ignored']]))->assertSessionHasNoErrors();
    $student = Student::query()->sole();
    expect(data_get($student->profile_details, 'custom_fields'))->toBe(['custom_1' => 'Evening', 'custom_2' => 'B12']);
    $settings['custom_fields'][1]['active'] = false;
    $this->put(route('administrators.system-management.student-configuration.update'), $settings)->assertSessionHasNoErrors();
    $this->put(route('administrators.students.update', $student), studentPolicyPayload($course, ['student_id' => $student->student_id, 'custom_fields' => ['custom_1' => 'Morning', 'custom_2' => 'tampered']]))->assertSessionHasNoErrors();
    expect(data_get($student->fresh()->profile_details, 'custom_fields'))->toBe(['custom_1' => 'Morning', 'custom_2' => 'B12']);
    $this->put(route('administrators.students.update', $student), studentPolicyPayload($course, ['student_id' => $student->student_id, 'custom_fields' => ['custom_1' => '']]))->assertSessionHasErrors('custom_fields.custom_1');
    $this->get(route('administrators.students.show', $student))->assertInertia(fn (Assert $page) => $page->has('student.custom_fields', 1)->where('student.custom_fields.0.label', 'Cohort')->where('student.custom_fields.0.value', 'Morning'));
    $settings['custom_fields'][0]['type'] = 'number';
    $this->put(route('administrators.system-management.student-configuration.update'), $settings)->assertSessionHasErrors('custom_fields.0.type');
    $settings['custom_fields'] = [];
    $this->put(route('administrators.system-management.student-configuration.update'), $settings)->assertSessionHasErrors('custom_fields');
});

it('scopes custom student fields to each school and blocks unsupported required creation channels', function (): void {
    $settings = StudentConfigurationService::defaults();
    $settings['custom_fields'] = [['key' => 'custom_1', 'label' => 'Cohort', 'type' => 'text', 'active' => true, 'required' => true, 'options' => []]];
    app(StudentConfigurationService::class)->update($settings, $this->school->id);
    $other = School::factory()->create();
    expect(app(StudentConfigurationService::class)->get($other->id)['custom_fields'])->toBe([]);
    expect(fn () => app(IdentifierGenerator::class)->assignForNewStudent(['student_type' => 'college', 'academic_year' => 1, 'email' => 'staff@example.test']))
        ->toThrow(Illuminate\Validation\ValidationException::class);
    $this->get(route('enrollment.create'))->assertInertia(fn (Assert $page) => $page->component('enrollment/closed')->where('message', 'This institution collects additional information that cannot be submitted online. Please contact the school office to register.'));
    $this->post(route('enrollment.store'), [])->assertSessionHasErrors('custom_fields.custom_1');
});

it('preserves custom fields if an older settings client omits them', function (): void {
    $settings = StudentConfigurationService::defaults();
    $settings['custom_fields'] = [['key' => 'custom_1', 'label' => 'Cohort', 'type' => 'text', 'active' => true, 'required' => false, 'options' => []]];
    app(StudentConfigurationService::class)->update($settings);
    unset($settings['custom_fields']);
    $this->put(route('administrators.system-management.student-configuration.update'), $settings)->assertSessionHasNoErrors();
    expect(app(StudentConfigurationService::class)->get()['custom_fields'])->toHaveCount(1);
});

it('preserves an existing choice after its option is retired while rejecting it for new records', function (): void {
    $settings = StudentConfigurationService::defaults();
    $settings['custom_fields'] = [['key' => 'custom_1', 'label' => 'Cohort', 'type' => 'choice', 'active' => true, 'required' => false, 'options' => ['Evening']]];
    app(StudentConfigurationService::class)->update($settings);
    $course = Course::factory()->create();
    $student = Student::factory()->create(['course_id' => $course->id, 'profile_details' => ['custom_fields' => ['custom_1' => 'Morning']]]);
    $this->put(route('administrators.students.update', $student), studentPolicyPayload($course, ['student_id' => $student->student_id, 'custom_fields' => ['custom_1' => 'Morning']]))->assertSessionHasNoErrors();
    $this->post(route('administrators.students.store'), studentPolicyPayload($course, ['custom_fields' => ['custom_1' => 'Morning']]))->assertSessionHasErrors('custom_fields.custom_1');
    expect(data_get($student->fresh()->profile_details, 'custom_fields.custom_1'))->toBe('Morning');
});

it('validates custom field definitions before saving settings', function (): void {
    $settings = StudentConfigurationService::defaults();
    $settings['custom_fields'] = [['key' => 'profile.secret', 'label' => 'Invalid', 'type' => 'choice', 'active' => true, 'required' => true, 'options' => []]];
    $this->put(route('administrators.system-management.student-configuration.update'), $settings)->assertSessionHasErrors('custom_fields.0.key');
    $settings['custom_fields'][0]['key'] = 'valid_key';
    $this->put(route('administrators.system-management.student-configuration.update'), $settings)->assertSessionHasErrors('custom_fields.0.options');
});

it('validates custom number and date answers for student records', function (): void {
    $settings = StudentConfigurationService::defaults();
    $settings['custom_fields'] = [
        ['key' => 'custom_number', 'label' => 'Distance', 'type' => 'number', 'active' => true, 'required' => false, 'options' => []],
        ['key' => 'custom_date', 'label' => 'Start date', 'type' => 'date', 'active' => true, 'required' => false, 'options' => []],
    ];
    app(StudentConfigurationService::class)->update($settings);
    $course = Course::factory()->create();
    $this->post(route('administrators.students.store'), studentPolicyPayload($course, ['custom_fields' => ['custom_number' => 'not-a-number', 'custom_date' => 'not-a-date']]))
        ->assertSessionHasErrors(['custom_fields.custom_number', 'custom_fields.custom_date']);
    $this->post(route('administrators.students.store'), studentPolicyPayload($course, ['custom_fields' => ['custom_number' => '3.5', 'custom_date' => '2026-03-01']]))->assertSessionHasNoErrors();
    expect(data_get(Student::query()->sole()->profile_details, 'custom_fields'))->toBe(['custom_number' => '3.5', 'custom_date' => '2026-03-01']);
});

it('preserves hidden values and unchanged identifiers when editing under new policies', function (): void {
    $student = Student::factory()->create(['school_id' => $this->school->id, 'student_id' => 'OLD-42', 'religion' => 'Previously recorded']);
    $config = StudentConfigurationService::defaults();
    $config['fields']['religion']['visible'] = false;
    app(StudentConfigurationService::class)->update($config);
    $this->put(route('administrators.students.update', $student), studentPolicyPayload($student->Course, ['student_id' => 'OLD-42', 'religion' => '', 'stay' => true]))->assertSessionHasNoErrors();
    expect($student->fresh()->student_id)->toBe('OLD-42')->and($student->fresh()->religion)->toBe('Previously recorded');
});

it('does not replace the stored address when both address fields are hidden', function (): void {
    $student = Student::factory()->create(['school_id' => $this->school->id, 'address' => 'Original address']);
    $config = StudentConfigurationService::defaults();
    $config['fields']['current_address']['visible'] = false;
    $config['fields']['permanent_address']['visible'] = false;
    app(StudentConfigurationService::class)->update($config);
    $this->put(route('administrators.students.update', $student), studentPolicyPayload($student->Course, ['student_id' => $student->student_id]))->assertSessionHasNoErrors();
    expect($student->fresh()->address)->toBe('Original address');
});

it('keeps the legacy external identifier policy opt-in', function (): void {
    app(StudentConfigurationService::class)->update(StudentConfigurationService::defaults(legacy: true));
    $this->getJson(route('administrators.students.generate-id', ['type' => 'shs']))->assertSuccessful()->assertJson(['id' => null]);
    expect(app(StudentConfigurationService::class)->usesExternal('shs'))->toBeTrue()
        ->and(app(StudentConfigurationService::class)->usesExternal('college'))->toBeFalse();
});

it('does not reset counters when saving form preferences', function (): void {
    $sequence = app(IdentifierGenerator::class)->sequenceFor('student');
    $sequence->update(['next_number' => 500]);
    $this->put(route('administrators.system-management.student-configuration.update'), StudentConfigurationService::defaults(legacy: true))->assertSessionHasNoErrors();
    expect($sequence->fresh()->next_number)->toBe(500);
});

it('renders the configuration page with neutral presets', function (): void {
    $this->get(route('administrators.system-management.student-configuration.index'))->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('administrators/system-management/student-configuration')
            ->where('student_configuration.preset', 'neutral')->where('student_presets.legacy_ph.external_identifier.use_as_student_id', true));
});

it('rejects stale sequence settings instead of resetting a counter', function (): void {
    $generator = app(IdentifierGenerator::class);
    $configuration = $generator->configuration();
    $configuration['student']['expected_next_number'] = $configuration['student']['next_number'];
    $generator->generateStudentId();
    $this->put(route('administrators.system-management.identifiers.update'), $configuration)->assertSessionHasErrors('student.next_number');
    expect($generator->sequenceFor('student')->next_number)->toBe(2);
});

it('keeps separate category counters and their prefixes independent', function (): void {
    $policies = StudentConfigurationService::defaults();
    $policies['enabled_types'] = ['college', 'tesda'];
    app(StudentConfigurationService::class)->update($policies);
    $generator = app(IdentifierGenerator::class);
    $settings = $generator->configuration();
    $settings['student'] = array_replace($settings['student'], ['counter_scope' => 'by_type', 'prefix_mode' => 'by_type', 'type_prefixes' => ['college' => 'C-', 'tesda' => 'T-'], 'character_set' => 'alphanumeric']);
    $generator->updateConfiguration($settings);
    expect($generator->generateStudentId(StudentType::College))->toBe('C-000001')
        ->and($generator->generateStudentId(StudentType::TESDA))->toBe('T-000001')
        ->and($generator->generateStudentId(StudentType::College))->toBe('C-000002');
});

it('keeps category counters while synchronizing policy when shared numbering is re-enabled', function (): void {
    $configuration = StudentConfigurationService::defaults();
    $configuration['enabled_types'] = ['college', 'tesda'];
    app(StudentConfigurationService::class)->update($configuration);
    $generator = app(IdentifierGenerator::class);
    $settings = $generator->configuration();
    $settings['student'] = array_replace($settings['student'], ['counter_scope' => 'by_type', 'prefix_mode' => 'by_type', 'type_prefixes' => ['college' => 'C-', 'tesda' => 'T-'], 'character_set' => 'alphanumeric']);
    $generator->updateConfiguration($settings);
    expect($generator->generateStudentId(StudentType::College))->toBe('C-000001');
    $settings = $generator->configuration();
    $settings['student']['counter_scope'] = 'shared';
    $settings['student']['assignment_mode'] = 'manual';
    $generator->updateConfiguration($settings);
    $counter = App\Models\IdSequence::query()->where('scope', 'school:'.$this->school->id)->where('key', 'student:college')->firstOrFail();
    expect($counter->next_number)->toBe(2)->and($counter->assignment_mode)->toBe('manual');
});

it('continues issuing category numbers after the identifier settings page is viewed', function (): void {
    $policies = StudentConfigurationService::defaults();
    $policies['enabled_types'] = ['college', 'tesda'];
    app(StudentConfigurationService::class)->update($policies);
    $generator = app(IdentifierGenerator::class);
    $settings = $generator->configuration();
    $settings['student'] = array_replace($settings['student'], ['counter_scope' => 'by_type', 'prefix_mode' => 'by_type', 'type_prefixes' => ['college' => 'C-', 'tesda' => 'T-'], 'character_set' => 'alphanumeric']);
    $generator->updateConfiguration($settings);
    $this->get(route('administrators.system-management.identifiers.index'))->assertSuccessful();
    expect(DB::transaction(fn () => $generator->assign(StudentType::College, null, 'automatic')))->toBe('C-000001');
});

it('keeps an external reference separate from the school number by default', function (): void {
    $policies = StudentConfigurationService::defaults();
    $policies['external_identifier'] = array_replace($policies['external_identifier'], ['enabled' => true, 'student_types' => ['college'], 'required' => true, 'format' => 'digits', 'min_length' => 12, 'max_length' => 12]);
    app(StudentConfigurationService::class)->update($policies);
    $this->post(route('administrators.students.store'), studentPolicyPayload(Course::factory()->create(), ['lrn' => '012345678901']))->assertSessionHasNoErrors();
    expect(Student::query()->sole()->student_id)->toBe('000001')->and(Student::query()->sole()->lrn)->toBe('012345678901');
});

it('resolves signup against the candidate school policy without casting formatted numbers', function (): void {
    $student = Student::factory()->create(['school_id' => $this->school->id, 'student_id' => 'STU-000042', 'student_type' => StudentType::SeniorHighSchool, 'lrn' => null]);
    $resolved = app(App\Services\StudentOrganizationAssignmentService::class)->resolveForSignup($student->email, StudentType::SeniorHighSchool, 'STU-000042', $student->id, inputField: 'student_id');
    expect($resolved->id)->toBe($student->id);
});

it('allows the same public number in different schools but rejects duplicate numbers within one school', function (): void {
    Student::factory()->create(['school_id' => $this->school->id, 'student_id' => '000042']);
    Student::factory()->create(['school_id' => School::factory()->create()->id, 'student_id' => '000042']);
    $this->post(route('administrators.students.store'), studentPolicyPayload(Course::factory()->create(), ['identifier_assignment' => 'manual', 'student_id' => '000042']))->assertSessionHasErrors('student_id');
});

it('preserves existing IDs when enabling external-as-student-number compatibility later', function (): void {
    $student = Student::factory()->create(['school_id' => $this->school->id, 'student_id' => 'SCHOOL-42', 'lrn' => null]);
    $policies = StudentConfigurationService::defaults(legacy: true);
    $policies['external_identifier']['student_types'] = ['college'];
    app(StudentConfigurationService::class)->update($policies);
    $this->put(route('administrators.students.update', $student), studentPolicyPayload($student->Course, ['student_id' => 'SCHOOL-42', 'stay' => true]))->assertSessionHasNoErrors();
    expect($student->fresh()->student_id)->toBe('SCHOOL-42');
});

it('rejects changed identifiers on automatic-only edits but permits unrelated changes', function (): void {
    $student = Student::factory()->create(['school_id' => $this->school->id, 'student_id' => '000042']);
    app(IdentifierGenerator::class)->sequenceFor('student')->update(['assignment_mode' => 'automatic']);
    $this->put(route('administrators.students.update', $student), studentPolicyPayload($student->Course, ['student_id' => '000043']))->assertSessionHasErrors('student_id');
    $this->put(route('administrators.students.update', $student), studentPolicyPayload($student->Course, ['student_id' => '000042', 'first_name' => 'Updated']))->assertSessionHasNoErrors();
    expect($student->fresh()->student_id)->toBe('000042');
});

it('permits editing unchanged duplicate legacy identifiers without reassigning them', function (): void {
    $student = Student::factory()->create(['school_id' => $this->school->id, 'student_id' => '000042']);
    $duplicate = Student::factory()->create(['school_id' => $this->school->id, 'student_id' => '000043']);
    DB::table('students')->where('id', $duplicate->id)->update(['student_id' => '000042', 'identifier_key' => null]);
    $this->put(route('administrators.students.update', $duplicate), studentPolicyPayload($duplicate->Course, ['student_id' => '000042']))->assertSessionHasNoErrors();
    expect($student->fresh()->student_id)->toBe('000042')->and($duplicate->fresh()->student_id)->toBe('000042');
});

it('rejects identifier settings that generate numbers outside their own validation rules', function (): void {
    $settings = app(IdentifierGenerator::class)->configuration();
    $settings['student']['padding'] = 8;
    $settings['student']['enforce_length'] = true;
    $settings['student']['exact_length'] = 6;
    $this->put(route('administrators.system-management.identifiers.update'), $settings)->assertSessionHasErrors('student.next_number');
    expect(app(IdentifierGenerator::class)->sequenceFor('student')->padding)->toBe(6);
});

it('does not renumber a legacy external identifier when its validation policy changes', function (): void {
    $policies = StudentConfigurationService::defaults(legacy: true);
    $policies['external_identifier']['student_types'] = ['college'];
    $policies['external_identifier']['format'] = 'digits';
    $policies['external_identifier']['min_length'] = 12;
    $policies['external_identifier']['max_length'] = 12;
    app(StudentConfigurationService::class)->update($policies);
    $student = Student::factory()->create(['school_id' => $this->school->id, 'student_id' => 'OLD-REF', 'lrn' => 'OLD-REF']);
    $this->put(route('administrators.students.update', $student), studentPolicyPayload($student->Course, ['lrn' => 'OLD-REF']))->assertSessionHasNoErrors();
    expect($student->fresh()->student_id)->toBe('OLD-REF');
});

it('keeps formatted identifiers intact in public returning-student lookup', function (): void {
    $student = Student::factory()->create(['school_id' => $this->school->id, 'student_id' => 'STU-000042']);
    $this->postJson(route('enrollment.lookup'), ['email' => $student->email, 'student_id' => 'STU-000042'])
        ->assertSuccessful()->assertJsonPath('matched', true)->assertJsonPath('student.student_id', 'STU-000042');
    $this->postJson(route('enrollment.lookup'), ['email' => $student->email, 'student_id' => '42'])->assertJsonPath('matched', false);
});

it('rejects an undo belonging to another school even when public numbers coincide', function (): void {
    $student = Student::factory()->create(['school_id' => $this->school->id, 'student_id' => '000042']);
    $other = Student::factory()->create(['school_id' => School::factory()->create()->id, 'student_id' => '000042']);
    $change = App\Models\StudentIdChangeLog::query()->create([
        'old_student_id' => '000041', 'new_student_id' => '000042', 'student_name' => $other->full_name,
        'changed_by' => 'admin@example.test', 'backup_data' => ['student_record_id' => $other->id, 'school_id' => $other->school_id],
        'affected_records' => [], 'is_undone' => false,
    ]);
    $result = app(App\Services\StudentIdUpdateService::class)->undoStudentIdChange($change->id, $student);
    expect($result['success'])->toBeFalse()->and($student->fresh()->student_id)->toBe('000042')->and($other->fresh()->student_id)->toBe('000042');
});

it('rolls back counter allocation when student creation fails in its transaction', function (): void {
    $generator = app(IdentifierGenerator::class);
    $generator->sequenceFor('student');
    try {
        DB::transaction(function () use ($generator): void {
            $generator->assign(StudentType::College, null, 'automatic');
            throw new RuntimeException('Simulated insert failure');
        });
    } catch (RuntimeException) {
        // The record and counter must share the same transaction.
    }
    expect($generator->previewStudentId())->toBe('000001');
});

it('applies numbering and disabled category policies to approved tool creation', function (): void {
    $generator = app(IdentifierGenerator::class);
    $generator->sequenceFor('student')->update(['prefix_mode' => 'static', 'prefix_value' => 'STU-', 'character_set' => 'alphanumeric']);
    expect($generator->assignForNewStudent(['student_type' => 'college', 'academic_year' => 1, 'email' => 'tool@example.test']))->toBe('STU-000001');
    expect(fn () => $generator->assignForNewStudent(['student_type' => 'shs', 'academic_year' => 11]))->toThrow(Illuminate\Validation\ValidationException::class);
    $generator->sequenceFor('student')->update(['assignment_mode' => 'manual']);
    expect(fn () => $generator->generateStudentId(StudentType::College))->toThrow(Illuminate\Validation\ValidationException::class);
});

it('does not let a secondary creation tool bypass a configured required field it cannot collect', function (): void {
    $policies = StudentConfigurationService::defaults();
    $policies['fields']['birthplace']['required'] = true;
    app(StudentConfigurationService::class)->update($policies);
    expect(fn () => app(IdentifierGenerator::class)->assignForNewStudent(['student_type' => 'college', 'academic_year' => 1, 'email' => 'tool@example.test']))
        ->toThrow(Illuminate\Validation\ValidationException::class);
    expect(Student::query()->count())->toBe(0);
});

it('rejects Filament enrollment student creation when a required institution field cannot be collected', function (): void {
    $settings = StudentConfigurationService::defaults();
    $settings['custom_fields'] = [['key' => 'custom_1', 'label' => 'Cohort', 'type' => 'text', 'active' => true, 'required' => true, 'options' => []]];
    app(StudentConfigurationService::class)->update($settings);
    expect(fn () => app(App\Services\StudentService::class)->createStudent([
        'student_type' => 'college', 'academic_year' => 1, 'first_name' => 'Amina', 'last_name' => 'Example',
        'email' => 'student@example.test', 'birth_date' => '2001-02-03', 'gender' => 'female', 'course_id' => Course::factory()->create()->id,
    ]))->toThrow(Illuminate\Validation\ValidationException::class);
    expect(Student::query()->count())->toBe(0);
});

it('keeps identifier settings reachable when a sequence is exhausted', function (): void {
    app(IdentifierGenerator::class)->sequenceFor('student')->update(['next_number' => 1000000000000]);
    $this->get(route('administrators.system-management.identifiers.index'))->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->has('id_sequences.student.preview_error'));
});

it('snapshots effective legacy configuration and counters without renumbering during upgrades', function (bool $existing): void {
    config(['database.connections.student_upgrade' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
    $connection = DB::connection('student_upgrade');
    $schema = $connection->getSchemaBuilder();
    $originalSchema = Schema::getFacadeRoot();
    $originalDefault = DB::getDefaultConnection();
    Schema::swap($schema);
    DB::setDefaultConnection('student_upgrade');
    try {
        $schema->create('schools', fn (Blueprint $table) => $table->id());
        $schema->create('students', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('school_id')->nullable();
            $table->integer('student_id')->nullable();
            $table->string('student_type')->nullable();
            $table->timestamps();
        });
        $schema->create('faculty', function (Blueprint $table): void {
            $table->id();
            $table->string('faculty_id_number')->nullable();
        });
        $schema->create('general_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('is_setup')->default(false);
        });
        (require database_path('migrations/2026_07_05_104138_create_id_sequences_table.php'))->up();
        (require database_path('migrations/2026_10_05_120000_add_configuration_fields_to_id_sequences_table.php'))->up();
        if ($existing) {
            $connection->table('schools')->insert(['id' => 7]);
            $connection->table('students')->insert(['id' => 9, 'school_id' => 7, 'student_id' => 208424, 'student_type' => 'college']);
            $connection->table('id_sequences')->insert(['key' => 'student', 'label' => 'Student IDs', 'start_number' => 200000, 'next_number' => 208425, 'increment_by' => 1, 'padding' => 6, 'prefix_mode' => 'by_type']);
        }
        (require database_path('migrations/2026_11_01_000001_create_student_configurations.php'))->up();
        (require database_path('migrations/2026_11_01_000002_scope_and_format_student_identifiers.php'))->up();
        if ($existing) {
            $saved = json_decode($connection->table('student_configurations')->where('scope', 'school:7')->value('configuration'), true);
            expect($saved['external_identifier']['use_as_student_id'])->toBeTrue()
                ->and($connection->table('id_sequences')->where('scope', 'school:7')->where('key', 'student')->value('next_number'))->toBe(208425)
                ->and((string) $connection->table('students')->where('id', 9)->value('student_id'))->toBe('208424');
        } else {
            expect($connection->table('student_configurations')->count())->toBe(0)->and($connection->table('id_sequences')->count())->toBe(0);
        }
    } finally {
        Schema::swap($originalSchema);
        DB::setDefaultConnection($originalDefault);
        DB::purge('student_upgrade');
    }
})->with([true, false]);

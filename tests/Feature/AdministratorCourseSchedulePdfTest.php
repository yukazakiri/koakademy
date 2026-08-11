<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Classes;
use App\Models\Course;
use App\Models\Department;
use App\Models\GeneralSetting;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\School;
use App\Models\Subject;
use App\Models\User;
use App\Services\CourseScheduleExportService;
use App\Services\TenantContext;
use App\Settings\SiteSettings;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

beforeEach(function (): void {
    GeneralSetting::factory()->create([
        'site_name' => 'Data Center College of the Philippines',
        'school_portal_title' => 'Data Center College of the Philippines',
        'school_starting_date' => '2026-06-22',
        'school_ending_date' => '2027-03-31',
        'semester' => 1,
        'support_email' => 'registrar@example.edu',
        'support_phone' => '442-4160',
    ]);

    $siteSettings = app(SiteSettings::class);
    $siteSettings->organization_address = '118 Bonifacio Street, Baguio City';
    $siteSettings->save();

    $this->school = School::factory()->create();
    app(TenantContext::class)->setCurrentSchool($this->school);
    $this->department = Department::factory()->create(['school_id' => $this->school->id]);
    $this->admin = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => $this->school->id,
    ]);
    $this->course = Course::factory()->create([
        'school_id' => $this->school->id,
        'department_id' => $this->department->id,
        'code' => 'BSHM',
        'title' => 'Bachelor of Science in Hospitality Management',
    ]);
});

it('protects the program schedule PDF route', function (): void {
    $path = "/administrators/scheduling-analytics/courses/{$this->course->id}/pdf";

    $this->get(portalUrlForAdministrators($path))->assertRedirect();

    $student = User::factory()->create([
        'role' => UserRole::Student,
        'school_id' => $this->school->id,
    ]);

    $this->actingAs($student)
        ->get(portalUrlForAdministrators($path))
        ->assertForbidden();

    $otherSchool = School::factory()->create();
    $otherDepartment = Department::factory()->create(['school_id' => $otherSchool->id]);
    $otherCourse = Course::factory()->create([
        'school_id' => $otherSchool->id,
        'department_id' => $otherDepartment->id,
    ]);

    $this->actingAs($this->admin)
        ->get(portalUrlForAdministrators("/administrators/scheduling-analytics/courses/{$otherCourse->id}/pdf"))
        ->assertNotFound();
});

it('returns an inline branded PDF with current program schedules grouped for printing', function (): void {
    $room = Room::factory()->create(['name' => '501']);
    $firstYearSubject = Subject::factory()->for($this->course)->create([
        'code' => 'GE-1',
        'title' => 'Understanding the Self',
        'units' => 3,
        'academic_year' => 1,
    ]);
    $secondYearSubject = Subject::factory()->for($this->course)->create([
        'code' => 'HPC-2',
        'title' => 'Fundamentals in Lodging Operations',
        'units' => 5,
        'academic_year' => 2,
    ]);

    $legacyThirdYearClass = Classes::factory()->create([
        'school_id' => $this->school->id,
        'subject_id' => null,
        'subject_code' => 'LEGACY-1',
        'academic_year' => 3,
        'semester' => 1,
        'school_year' => '2026 - 2027',
        'course_codes' => [$this->course->id],
        'section' => 'A',
        'classification' => 'college',
    ]);
    $sectionA = Classes::factory()->create([
        'school_id' => $this->school->id,
        'subject_id' => $firstYearSubject->id,
        'subject_code' => $firstYearSubject->code,
        'academic_year' => 1,
        'semester' => 1,
        'school_year' => '2026 - 2027',
        'course_codes' => [(string) $this->course->id],
        'section' => 'A',
        'classification' => 'college',
    ]);
    $firstYearSectionB = Classes::factory()->create([
        'school_id' => $this->school->id,
        'subject_id' => $firstYearSubject->id,
        'subject_code' => $firstYearSubject->code,
        'academic_year' => 1,
        'semester' => 1,
        'school_year' => '2026 - 2027',
        'course_codes' => [$this->course->id],
        'section' => 'B',
        'classification' => 'college',
    ]);
    $sectionB = Classes::factory()->create([
        'school_id' => $this->school->id,
        'subject_id' => $secondYearSubject->id,
        'subject_code' => $secondYearSubject->code,
        'academic_year' => 2,
        'semester' => 1,
        'school_year' => '2026-2027',
        'course_codes' => [$this->course->id],
        'section' => 'B',
        'classification' => 'college',
    ]);

    foreach (['Monday', 'Wednesday', 'Friday'] as $day) {
        Schedule::factory()->create([
            'class_id' => $sectionA->id,
            'room_id' => $room->id,
            'day_of_week' => $day,
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
        ]);
    }

    foreach (['Monday', 'Wednesday', 'Friday'] as $day) {
        Schedule::factory()->create([
            'class_id' => $firstYearSectionB->id,
            'room_id' => $room->id,
            'day_of_week' => $day,
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
        ]);
    }

    Schedule::factory()->create([
        'class_id' => $sectionA->id,
        'room_id' => null,
        'day_of_week' => 'Tuesday',
        'start_time' => '13:00:00',
        'end_time' => '14:30:00',
    ]);
    foreach (['Tuesday', 'Thursday'] as $day) {
        Schedule::factory()->create([
            'class_id' => $sectionB->id,
            'room_id' => $room->id,
            'day_of_week' => $day,
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
        ]);
    }
    Schedule::factory()->create([
        'class_id' => $legacyThirdYearClass->id,
        'room_id' => $room->id,
        'day_of_week' => 'Friday',
        'start_time' => '13:00:00',
        'end_time' => '14:00:00',
    ]);

    $otherCourse = Course::factory()->create([
        'school_id' => $this->school->id,
        'department_id' => $this->department->id,
    ]);
    $unrelatedClass = Classes::factory()->create([
        'school_id' => $this->school->id,
        'subject_id' => $firstYearSubject->id,
        'subject_code' => 'OTHER-101',
        'academic_year' => 1,
        'semester' => 1,
        'school_year' => '2026 - 2027',
        'course_codes' => [$otherCourse->id],
        'classification' => 'college',
    ]);
    Schedule::factory()->create(['class_id' => $unrelatedClass->id]);

    $oldClass = Classes::factory()->create([
        'school_id' => $this->school->id,
        'subject_id' => $firstYearSubject->id,
        'subject_code' => 'OLD-101',
        'academic_year' => 1,
        'semester' => 2,
        'school_year' => '2025 - 2026',
        'course_codes' => [$this->course->id],
        'classification' => 'college',
    ]);
    Schedule::factory()->create(['class_id' => $oldClass->id]);

    $otherSchool = School::factory()->create();
    $otherSchoolClass = Classes::factory()->create([
        'school_id' => $otherSchool->id,
        'subject_id' => $firstYearSubject->id,
        'subject_code' => 'REMOTE-101',
        'academic_year' => 1,
        'semester' => 1,
        'school_year' => '2026 - 2027',
        'course_codes' => [$this->course->id],
        'classification' => 'college',
    ]);
    Schedule::factory()->create(['class_id' => $otherSchoolClass->id]);

    Pdf::fake();

    $response = $this->actingAs($this->admin)->get(portalUrlForAdministrators(
        "/administrators/scheduling-analytics/courses/{$this->course->id}/pdf"
    ));

    $response->assertOk();

    $generatedPdf = null;
    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) use (&$generatedPdf): bool {
        $generatedPdf = $pdf;

        return $pdf->isInline()
            && $pdf->viewName === 'pdf.course-schedule-sheet'
            && $pdf->downloadName === 'course-schedule-bshm-2026-2027-semester-1.pdf';
    });

    expect($generatedPdf)->toBeInstanceOf(PdfBuilder::class)
        ->and($generatedPdf->format)->toBe('a4')
        ->and($generatedPdf->orientation)->toBe('Portrait')
        ->and($generatedPdf->margins)->toBe([
            'top' => 8.0,
            'right' => 8.0,
            'bottom' => 10.0,
            'left' => 8.0,
            'unit' => 'mm',
        ]);

    $viewData = $generatedPdf->viewData;
    $groups = collect($viewData['year_groups'])->keyBy('year');
    $firstYearRows = collect($groups->get('1')['rows']);
    $secondYearRows = collect($groups->get('2')['rows']);
    $thirdYearRows = collect($groups->get('3')['rows']);

    expect($viewData['school']['name'])->toBe('Data Center College of the Philippines')
        ->and($viewData['school']['address'])->toBe('118 Bonifacio Street, Baguio City')
        ->and($viewData['school']['phone'])->toBe('442-4160')
        ->and($viewData['course']['code'])->toBe('BSHM')
        ->and($viewData['semester_label'])->toBe('1st Semester')
        ->and($viewData['school_year'])->toBe('2026 - 2027')
        ->and($groups->keys()->all())->toBe([1, 2, 3])
        ->and($groups->get('1')['label'])->toBe('FIRST YEAR')
        ->and($groups->get('2')['label'])->toBe('SECOND YEAR')
        ->and($groups->get('3')['label'])->toBe('THIRD YEAR')
        ->and($firstYearRows)->toHaveCount(3)
        ->and($firstYearRows->pluck('day')->all())->toBe(['MWF', 'T', 'MWF'])
        ->and($firstYearRows->firstWhere('day', 'MWF')['time'])->toBe('8:00 AM – 9:00 AM')
        ->and($firstYearRows->firstWhere('day', 'MWF')['room'])->toBe('501')
        ->and($firstYearRows->firstWhere('day', 'T')['room'])->toBe('—')
        ->and($firstYearRows->first()['section'])->toBe('A')
        ->and($firstYearRows->first()['units'])->toBe(3)
        ->and($firstYearRows->where('section', 'B'))->toHaveCount(1)
        ->and($secondYearRows)->toHaveCount(1)
        ->and($secondYearRows->first()['day'])->toBe('TTH')
        ->and($secondYearRows->first()['section'])->toBe('B')
        ->and($secondYearRows->first()['units'])->toBe(5)
        ->and($thirdYearRows)->toHaveCount(1)
        ->and($thirdYearRows->first()['code'])->toBe('LEGACY-1')
        ->and($thirdYearRows->first()['units'])->toBeNull();

    $html = $generatedPdf->getHtml();

    expect($html)->toContain('Bachelor of Science in Hospitality Management')
        ->and($html)->toContain('Course Code')
        ->and($html)->toContain('Descriptive Title')
        ->and($html)->toContain('—')
        ->and($html)->not->toContain('OTHER-101')
        ->and($html)->not->toContain('OLD-101')
        ->and($html)->not->toContain('REMOTE-101');
});

it('returns not found when the program has no current schedules', function (): void {
    Pdf::fake();

    $this->actingAs($this->admin)
        ->get(portalUrlForAdministrators("/administrators/scheduling-analytics/courses/{$this->course->id}/pdf"))
        ->assertNotFound();
});

it('places a shared class under each programs matching curriculum year', function (): void {
    $otherCourse = Course::factory()->create([
        'school_id' => $this->school->id,
        'department_id' => $this->department->id,
        'code' => 'BSBA',
        'title' => 'Bachelor of Science in Business Administration',
    ]);
    $hospitalitySubject = Subject::factory()->for($this->course)->create([
        'code' => 'FS-HM',
        'title' => 'Feasibility Study',
        'academic_year' => 4,
        'units' => 3,
    ]);
    $businessSubject = Subject::factory()->for($otherCourse)->create([
        'code' => 'FS-BA',
        'title' => 'Feasibility Study',
        'academic_year' => 3,
        'units' => 3,
    ]);
    $sharedClass = Classes::factory()->create([
        'school_id' => $this->school->id,
        'subject_id' => $hospitalitySubject->id,
        'subject_ids' => [$hospitalitySubject->id, $businessSubject->id],
        'subject_code' => 'FS',
        'academic_year' => 1,
        'semester' => 1,
        'school_year' => '2026 - 2027',
        'course_codes' => [(string) $this->course->id, $otherCourse->id],
        'section' => 'A',
        'classification' => 'college',
    ]);
    Schedule::factory()->create([
        'class_id' => $sharedClass->id,
        'day_of_week' => 'Monday',
        'start_time' => '08:00:00',
        'end_time' => '09:00:00',
    ]);

    $export = app(CourseScheduleExportService::class);
    $hospitalityGroups = collect($export->build($this->course)['year_groups'])->keyBy('year');
    $businessGroups = collect($export->build($otherCourse)['year_groups'])->keyBy('year');

    expect($hospitalityGroups->keys()->all())->toBe([4])
        ->and($hospitalityGroups->get('4')['rows'])->toHaveCount(1)
        ->and($hospitalityGroups->get('4')['rows'][0]['code'])->toBe('FS-HM')
        ->and($businessGroups->keys()->all())->toBe([3])
        ->and($businessGroups->get('3')['rows'])->toHaveCount(1)
        ->and($businessGroups->get('3')['rows'][0]['code'])->toBe('FS-BA');
});

<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Resource;
use App\Models\School;
use App\Models\StudentEnrollment;
use App\Models\User;
use FPDF;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($this->admin)
        ->withSession(['current_school_id' => $this->school->id]);
});

function makeAssessmentPdfBytes(string $label): string
{
    $pdf = new FPDF;
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(40, 10, $label);

    return (string) $pdf->Output('S');
}

function createAssessmentResource(StudentEnrollment $enrollment, string $disk, string $path, string $contents): void
{
    Storage::disk($disk)->put($path, $contents);

    Resource::query()->create([
        'resourceable_id' => $enrollment->id,
        'resourceable_type' => $enrollment::class,
        'name' => basename($path),
        'type' => 'assessment',
        'file_path' => $path,
        'file_name' => basename($path),
        'mime_type' => 'application/pdf',
        'disk' => $disk,
        'file_size' => mb_strlen($contents),
    ]);
}

it('validates the bulk enrollment action payload', function (): void {
    $url = portalUrlForAdministrators('/administrators/enrollments/bulk-destroy');

    $this->postJson($url, [])->assertStatus(422);

    $this->postJson($url, ['ids' => range(1, 101)])->assertStatus(422);
});

it('soft deletes the selected enrollments in one request', function (): void {
    $targets = StudentEnrollment::factory()->count(2)->create(['school_id' => $this->school->id]);
    $untouched = StudentEnrollment::factory()->create(['school_id' => $this->school->id]);

    $response = $this->post(portalUrlForAdministrators('/administrators/enrollments/bulk-destroy'), [
        'ids' => $targets->pluck('id')->all(),
    ]);

    $response->assertRedirect();

    foreach ($targets as $target) {
        $this->assertSoftDeleted($target->fresh());
    }

    expect($untouched->fresh()->deleted_at)->toBeNull();
});

it('ignores enrollments that belong to another school when bulk deleting', function (): void {
    $foreign = StudentEnrollment::factory()->create([
        'school_id' => School::factory()->create()->id,
    ]);

    $response = $this->post(portalUrlForAdministrators('/administrators/enrollments/bulk-destroy'), [
        'ids' => [$foreign->id],
    ]);

    $response->assertRedirect();

    expect($foreign->fresh()->deleted_at)->toBeNull();
});

it('permanently deletes selected enrollments and their related resources', function (): void {
    Storage::fake('assessment-bulk-test');

    $enrollment = StudentEnrollment::factory()->create(['school_id' => $this->school->id]);
    createAssessmentResource($enrollment, 'assessment-bulk-test', 'assessments/one.pdf', makeAssessmentPdfBytes('One'));

    $response = $this->post(portalUrlForAdministrators('/administrators/enrollments/bulk-force-destroy'), [
        'ids' => [$enrollment->id],
    ]);

    $response->assertRedirect();

    expect(StudentEnrollment::withTrashed()->find($enrollment->id))->toBeNull();
    expect(Resource::query()->where('resourceable_id', $enrollment->id)->where('type', 'assessment')->exists())->toBeFalse();
});

it('returns 404 when none of the selected enrollments have an assessment file', function (): void {
    $enrollments = StudentEnrollment::factory()->count(2)->create(['school_id' => $this->school->id]);

    $this->postJson(portalUrlForAdministrators('/administrators/enrollments/bulk-export-assessments'), [
        'ids' => $enrollments->pluck('id')->all(),
    ])
        ->assertStatus(404)
        ->assertJson(['message' => 'No assessment files were found for the selected enrollments.']);
});

it('merges the assessment pdfs of selected enrollments into one download', function (): void {
    Storage::fake('assessment-bulk-test');

    $first = StudentEnrollment::factory()->create(['school_id' => $this->school->id]);
    $second = StudentEnrollment::factory()->create(['school_id' => $this->school->id]);
    $skipped = StudentEnrollment::factory()->create(['school_id' => $this->school->id]);

    createAssessmentResource($first, 'assessment-bulk-test', 'assessments/first.pdf', makeAssessmentPdfBytes('First'));
    createAssessmentResource($second, 'assessment-bulk-test', 'assessments/second.pdf', makeAssessmentPdfBytes('Second'));

    $response = $this->post(portalUrlForAdministrators('/administrators/enrollments/bulk-export-assessments'), [
        'ids' => [$first->id, $skipped->id, $second->id],
    ]);

    $response->assertOk();
    $response->assertDownload();

    $mergedFile = $response->getFile();

    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and($response->headers->get('x-assessment-count'))->toBe('2')
        ->and($response->headers->get('x-assessment-skipped-count'))->toBe('1')
        ->and($response->headers->get('content-disposition'))->toContain('bulk-assessments-')
        ->and(file_get_contents($mergedFile->getPathname()))->toStartWith('%PDF-');
});

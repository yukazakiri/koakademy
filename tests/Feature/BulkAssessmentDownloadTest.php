<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\AssessmentExport;
use App\Models\School;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Storage;

it('allows only the owner in the active school to download a ready assessment export', function (): void {
    config()->set('assessment-exports.disk', 'local');
    Storage::fake('local');
    $school = School::factory()->create();
    app(TenantContext::class)->setCurrentSchoolId($school->id);
    $owner = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $school->id]);
    $other = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $school->id]);
    $export = AssessmentExport::withoutSchoolScope()->create([
        'user_id' => $owner->id,
        'school_id' => $school->id,
        'status' => 'completed',
        'stage' => 'ready',
        'filters' => [],
        'output_disk' => 'local',
        'output_path' => sprintf('assessment-exports/%d/%d/export/final/bulk-assessments.pdf', $school->id, $owner->id),
        'output_name' => 'bulk-assessments.pdf',
        'completed_at' => now(),
    ]);
    Storage::disk('local')->put($export->output_path, '%PDF-1.4 test');

    $this->actingAs($owner)
        ->get(portalUrlForAdministrators('/download/bulk-assessment/'.$export->id))
        ->assertSuccessful()
        ->assertDownload('bulk-assessments.pdf');
    $this->actingAs($other)
        ->get(portalUrlForAdministrators('/download/bulk-assessment/'.$export->id))
        ->assertNotFound();
});

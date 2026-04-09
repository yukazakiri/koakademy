<?php

declare(strict_types=1);

use App\Models\ShsStrand;
use App\Models\ShsStudent;
use App\Models\ShsTrack;
use App\Models\StrandSubject;
use Illuminate\Support\Facades\DB;

test('strand deletion cascades to strand subjects', function (): void {
    // Create a track and strand
    $track = ShsTrack::factory()->create([
        'track_name' => 'Academic Track',
    ]);

    $strand = ShsStrand::factory()->create([
        'strand_name' => 'STEM',
        'track_id' => $track->id,
    ]);

    // Create multiple subjects for the strand
    $subject1 = StrandSubject::factory()->create([
        'strand_id' => $strand->id,
        'code' => 'MATH101',
        'title' => 'Advanced Mathematics',
    ]);

    $subject2 = StrandSubject::factory()->create([
        'strand_id' => $strand->id,
        'code' => 'PHY101',
        'title' => 'Physics',
    ]);

    $subject3 = StrandSubject::factory()->create([
        'strand_id' => $strand->id,
        'code' => 'CHEM101',
        'title' => 'Chemistry',
    ]);

    // Verify subjects exist
    expect($strand->subjects)->toHaveCount(3);
    expect(StrandSubject::count())->toBe(3);

    // Create a student enrolled in the strand (should remain but strand_id becomes null)
    $student = ShsStudent::factory()->create([
        'strand_id' => $strand->id,
        'track_id' => $track->id,
    ]);

    expect($student->fresh()->strand_id)->toBe($strand->id);

    // Delete the strand
    $strandId = $strand->id;
    $strand->delete();

    // Verify strand is deleted
    expect(ShsStrand::find($strandId))->toBeNull();

    // Verify all strand subjects are also deleted (cascade delete)
    expect(StrandSubject::find($subject1->id))->toBeNull();
    expect(StrandSubject::find($subject2->id))->toBeNull();
    expect(StrandSubject::find($subject3->id))->toBeNull();
    expect(StrandSubject::count())->toBe(0);

    // Verify student still exists but strand_id is set to null (due to database constraint)
    expect(ShsStudent::find($student->id))->not->toBeNull();
    expect($student->fresh()->strand_id)->toBeNull();
});

test('strand subjects are properly associated with strand before deletion', function (): void {
    $track = ShsTrack::factory()->create();
    $strand = ShsStrand::factory()->create(['track_id' => $track->id]);

    $subject = StrandSubject::factory()->create(['strand_id' => $strand->id]);

    // Verify relationship
    expect($subject->strand->is($strand))->toBeTrue();
    expect($strand->subjects->contains($subject))->toBeTrue();
    expect($strand->subjects()->count())->toBe(1);

    // Delete strand
    $strand->delete();

    // Verify subject is gone
    expect(StrandSubject::find($subject->id))->toBeNull();
});

test('database foreign key constraint supports cascade delete', function (): void {
    $track = ShsTrack::factory()->create();
    $strand = ShsStrand::factory()->create(['track_id' => $track->id]);

    $subject = StrandSubject::factory()->create(['strand_id' => $strand->id]);

    // Directly delete from database to test foreign key constraint
    DB::table('shs_strands')->where('id', $strand->id)->delete();

    // Verify subject is also deleted by database cascade
    expect(DB::table('strand_subjects')->where('id', $subject->id)->count())->toBe(0);
});

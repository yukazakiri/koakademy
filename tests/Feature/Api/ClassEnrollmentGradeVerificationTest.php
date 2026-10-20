<?php

declare(strict_types=1);

use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Event;

/**
 * Regressions for the grade verification endpoint.
 *
 * The endpoint eager loaded a "verifier" relation the model never declared, so
 * every request died with a RelationNotFoundException that the controller
 * turned into a 500 — and it did so *after* the row had already been written.
 */
beforeEach(function (): void {
    $this->class = Classes::factory()->create();
    $this->student = Student::factory()->create();
    $this->enrollment = ClassEnrollment::factory()->create([
        'class_id' => $this->class->id,
        'student_id' => $this->student->id,
        'is_grades_finalized' => true,
        'is_grades_verified' => false,
        'verified_by' => null,
        'verified_at' => null,
    ]);
});

it('verifies grades and returns the verifier instead of a 500', function (): void {
    $verifier = User::factory()->create(['name' => 'Registrar One']);

    $response = $this->actingAs($verifier, 'sanctum')
        ->postJson("/api/class-enrollments/{$this->enrollment->id}/verify", [
            'verification_notes' => 'Matched against the registrar copy.',
        ]);

    $response->assertOk()
        ->assertJsonPath('message', 'Grades verified successfully')
        ->assertJsonPath('data.is_grades_verified', true)
        ->assertJsonPath('data.verified_by', $verifier->id)
        ->assertJsonPath('data.verification_notes', 'Matched against the registrar copy.')
        ->assertJsonPath('data.verifier.id', $verifier->id)
        ->assertJsonPath('data.verifier.name', 'Registrar One');

    $enrollment = $this->enrollment->fresh();

    expect($enrollment->is_grades_verified)->toBeTrue()
        ->and($enrollment->verified_by)->toBe($verifier->id)
        ->and($enrollment->verified_at)->not->toBeNull();
});

it('exposes the verifier on the enrollment detail endpoint', function (): void {
    $verifier = User::factory()->create(['name' => 'Registrar Two']);

    $this->enrollment->update([
        'is_grades_verified' => true,
        'verified_by' => $verifier->id,
        'verified_at' => now(),
    ]);

    $response = $this->actingAs($verifier, 'sanctum')
        ->getJson("/api/class-enrollments/{$this->enrollment->id}");

    $response->assertOk()
        ->assertJsonPath('data.verifier.id', $verifier->id)
        ->assertJsonPath('data.verifier.name', 'Registrar Two');
});

it('leaves no partial write behind when verification fails', function (): void {
    // Blow up right after the row is written but still inside the transaction.
    Event::listen('eloquent.saved: '.ClassEnrollment::class, function (): void {
        throw new RuntimeException('simulated failure after the write');
    });

    $verifier = User::factory()->create();

    $response = $this->actingAs($verifier, 'sanctum')
        ->postJson("/api/class-enrollments/{$this->enrollment->id}/verify");

    $response->assertStatus(500)
        ->assertJsonPath('message', 'Failed to verify grades');

    $enrollment = $this->enrollment->fresh();

    expect($enrollment->is_grades_verified)->toBeFalse()
        ->and($enrollment->verified_by)->toBeNull()
        ->and($enrollment->verified_at)->toBeNull();
});

it('refuses to verify grades that were never finalized', function (): void {
    $this->enrollment->update(['is_grades_finalized' => false]);

    $verifier = User::factory()->create();

    $response = $this->actingAs($verifier, 'sanctum')
        ->postJson("/api/class-enrollments/{$this->enrollment->id}/verify");

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Cannot verify grades. Grades must be finalized first.');

    expect($this->enrollment->fresh()->is_grades_verified)->toBeFalse();
});

it('rejects a non-string verification note without touching the enrollment', function (): void {
    $verifier = User::factory()->create();

    $response = $this->actingAs($verifier, 'sanctum')
        ->postJson("/api/class-enrollments/{$this->enrollment->id}/verify", [
            'verification_notes' => ['not', 'a', 'string'],
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['verification_notes']);

    expect($this->enrollment->fresh()->is_grades_verified)->toBeFalse();
});

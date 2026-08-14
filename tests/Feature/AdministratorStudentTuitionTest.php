<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;
use Spatie\Permission\Models\Permission;

it('redirects the legacy student tuition action to the canonical workspace', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    Permission::findOrCreate('manage_tuition_fees', 'web');
    $admin->givePermissionTo('manage_tuition_fees');
    $student = Student::factory()->create();

    $this->actingAs($admin)
        ->patch(route('administrators.students.update-tuition', $student->id), [
            'total_lectures' => 16000,
            'total_laboratory' => 3000,
        ])
        ->assertRedirect(route('administrators.finance.tuition-adjustments.index', [
            'student' => $student->id,
        ]));
});

it('forbids the legacy student tuition action without finance permission', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $student = Student::factory()->create();

    $this->actingAs($admin)
        ->patch(route('administrators.students.update-tuition', $student->id))
        ->assertForbidden();
});

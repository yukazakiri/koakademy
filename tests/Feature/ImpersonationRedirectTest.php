<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ImpersonationRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_redirects_to_student_dashboard_when_impersonating_student(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $student = User::factory()->create(['role' => UserRole::Student]);

        $this->actingAs($admin)
            ->post(route('administrators.users.impersonate', $student))
            ->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticatedAs($student);
    }

    public function test_admin_redirects_to_faculty_dashboard_when_impersonating_faculty(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $faculty = User::factory()->create(['role' => UserRole::Professor]);

        $this->actingAs($admin)
            ->post(route('administrators.users.impersonate', $faculty))
            ->assertRedirect(route('faculty.dashboard'));

        $this->assertAuthenticatedAs($faculty);
    }

    public function test_admin_redirects_to_admin_dashboard_when_impersonating_admin(): void
    {
        // SuperAdmin can impersonate Admin
        $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($superAdmin)
            ->post(route('administrators.users.impersonate', $admin))
            ->assertRedirect(route('administrators.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Department;
use App\Models\School;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ProfileApiTest extends TestCase
{
    public function test_profile_me_returns_legacy_department_string_without_department_id(): void
    {
        $user = User::factory()->create([
            'department_id' => null,
            'department' => 'Computing Sciences',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/profile/me');

        $response->assertOk();
        $response->assertJsonPath('data.user.department', 'Computing Sciences');
        $this->assertIsString($response->json('data.user.department'));
    }

    public function test_profile_me_returns_legacy_department_string_with_department_id_present(): void
    {
        $school = School::factory()->create();
        $department = Department::factory()->forSchool($school)->create(['name' => 'College of Computing']);

        $user = User::factory()->create([
            'school_id' => $school->id,
            'department_id' => $department->id,
            'department' => 'Computing Sciences Legacy',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/profile/me');

        $response->assertOk();
        $response->assertJsonPath('data.user.department', 'Computing Sciences Legacy');
        $this->assertIsString($response->json('data.user.department'));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Faculty;

use App\Enums\UserRole;
use App\Models\Faculty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ClassRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_faculty_can_access_classes_index()
    {
        // Create a faculty user with required verification fields
        $user = User::factory()->create([
            'role' => UserRole::Instructor,
            'email' => 'faculty@example.com',
            'faculty_id_number' => 'FAC-12345', // Required by middleware
        ]);

        // Create faculty profile
        $faculty = Faculty::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'first_name' => 'Test',
            'last_name' => 'Faculty',
            'email' => $user->email,
            'password' => bcrypt('password'),
            'status' => 'active',
            'faculty_id_number' => 'FAC-12345',
        ]);

        $this->actingAs($user);

        // Test the index route
        $response = $this->get('/faculty/classes');
        $response->assertStatus(200);
    }
}

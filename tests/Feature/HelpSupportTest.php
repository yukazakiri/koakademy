<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class HelpSupportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['inertia.testing.ensure_pages_exist' => false]);
    }

    public function test_user_can_view_help_page()
    {
        $user = User::factory()->create(['role' => 'student']);

        $this->actingAs($user)
            ->get(route('student.help.index'))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Help/Index')
                ->has('tickets')
            );
    }

    public function test_user_can_submit_support_ticket()
    {
        $user = User::factory()->create(['role' => 'student']);

        $this->actingAs($user)
            ->post(route('student.help.store'), [
                'type' => 'support',
                'subject' => 'Need help with login',
                'message' => 'I cannot login to my account',
                'priority' => 'high',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('help_tickets', [
            'user_id' => $user->id,
            'type' => 'support',
            'subject' => 'Need help with login',
            'priority' => 'high',
        ]);
    }

    public function test_user_can_submit_issue()
    {
        $user = User::factory()->create(['role' => 'student']);

        $this->actingAs($user)
            ->post(route('student.help.store'), [
                'type' => 'issue',
                'subject' => 'Bug in grade submission',
                'message' => 'System crashes when submitting grades',
                'priority' => 'medium',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('help_tickets', [
            'type' => 'issue',
            'subject' => 'Bug in grade submission',
        ]);
    }

    public function test_validation_errors()
    {
        $user = User::factory()->create(['role' => 'student']);

        $this->actingAs($user)
            ->post(route('student.help.store'), [
                'type' => 'invalid-type',
                'subject' => '',
            ])
            ->assertSessionHasErrors(['type', 'subject', 'message', 'priority']);
    }
}

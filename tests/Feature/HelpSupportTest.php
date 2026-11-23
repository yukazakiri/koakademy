<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\HelpTicket;
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

    public function test_submit_url_targets_the_portal_endpoint_directly()
    {
        $user = User::factory()->create(['role' => 'student']);

        $this->actingAs($user)
            ->get(route('student.help.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('submit_url', route('student.help.store'))
            );
    }

    public function test_legacy_help_post_redirect_does_not_copy_the_message_into_the_url()
    {
        $user = User::factory()->create(['role' => 'student']);

        $response = $this->actingAs($user)->post('/help', [
            'type' => 'support',
            'subject' => 'Private subject',
            'message' => 'Private message body',
            'priority' => 'low',
        ]);

        $response->assertStatus(307)->assertRedirect(route('student.help.store'));
        $this->assertStringNotContainsString('Private', (string) $response->headers->get('Location'));
    }

    public function test_ticket_page_does_not_expose_staff_contact_details_to_the_ticket_owner()
    {
        $student = User::factory()->create(['role' => 'student']);
        $staff = User::factory()->create([
            'role' => 'admin',
            'email' => 'staff-private@example.com',
            'phone' => '+63 900 000 0000',
        ]);

        $ticket = HelpTicket::create([
            'user_id' => $student->id,
            'type' => 'support',
            'subject' => 'Login problem',
            'message' => 'Cannot log in',
            'priority' => 'low',
        ]);
        $ticket->replies()->create(['user_id' => $staff->id, 'message' => 'Looking into it']);

        $this->actingAs($student)
            ->get(route('student.help.show', $ticket))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Help/Show')
                ->where('reply_url', route('student.help.reply', $ticket))
                ->has('ticket.replies.0.user.name')
                ->missing('ticket.replies.0.user.email')
                ->missing('ticket.replies.0.user.phone')
                ->missing('ticket.user.email')
            );
    }
}

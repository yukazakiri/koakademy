<?php

declare(strict_types=1);

use App\Enums\ClassPostType;
use App\Enums\UserRole;
use App\Models\Classes;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

it('rejects raw base64 data-URI attachments on POST /api/class-posts', function (): void {
    Storage::fake('private');

    $school = School::factory()->create();
    $admin = User::factory()->create([
        'role' => UserRole::SuperAdmin,
        'school_id' => $school->id,
    ]);
    $class = Classes::factory()->create(['school_id' => $school->id]);

    Sanctum::actingAs($admin, [config('api.abilities.read'), config('api.abilities.write')]);

    $this->postJson('/api/class-posts', [
        'class_id' => $class->id,
        'title' => 'Data URI attempt',
        'content' => 'Testing base64 rejection',
        'type' => ClassPostType::Announcement->value,
        'attachments' => [
            'data:text/html;base64,'.base64_encode('<script>alert(1)</script>'),
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['attachments.0']);

    expect(Storage::disk('private')->allFiles('class-post-attachments'))->toBe([]);
});

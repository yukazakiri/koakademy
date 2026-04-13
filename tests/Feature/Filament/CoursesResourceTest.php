<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\Courses\Pages\ListCourses;
use App\Models\Course;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    Filament::setCurrentPanel('admin');
});

it('displays courses in the admin courses table', function (): void {
    $course = Course::factory()->create([
        'code' => 'BSITQA',
        'title' => 'BS Information Technology QA',
    ]);

    Livewire::test(ListCourses::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$course]);
});

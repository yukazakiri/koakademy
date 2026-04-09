<?php

declare(strict_types=1);

use App\Filament\Resources\Classes\Pages\ListClasses;
use App\Models\Classes;
use App\Models\Subject;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    // Authenticate as admin user
    $user = User::factory()->create();
    $this->actingAs($user);
    Filament::setCurrentPanel('admin');
});

it('can search classes by single subject code', function (): void {
    // Create a class with a single subject
    $subject = Subject::factory()->create([
        'code' => 'PATHFIT 2',
        'title' => 'Physical Activity Towards Health and Fitness 2',
    ]);

    $class = Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_code' => 'PATHFIT 2',
        'subject_ids' => null,
        'section' => 'A',
        'classification' => 'college',
    ]);

    // Search for PATHFIT 2
    livewire(ListClasses::class)
        ->searchTable('PATHFIT 2')
        ->assertCanSeeTableRecords([$class]);
});

it('can search classes by multiple subject codes', function (): void {
    // Create multiple subjects
    $subject1 = Subject::factory()->create([
        'code' => 'PATHFIT 2',
        'title' => 'Physical Activity Towards Health and Fitness 2',
    ]);

    $subject2 = Subject::factory()->create([
        'code' => 'MATH 101',
        'title' => 'College Algebra',
    ]);

    // Create a class with multiple subjects
    $class = Classes::factory()->create([
        'subject_id' => null,
        'subject_code' => 'PATHFIT 2',
        'subject_ids' => [$subject1->id, $subject2->id],
        'section' => 'B',
        'classification' => 'college',
    ]);

    // Search for PATHFIT 2 should find this class
    livewire(ListClasses::class)
        ->searchTable('PATHFIT 2')
        ->assertCanSeeTableRecords([$class]);

    // Search for MATH 101 should also find this class
    livewire(ListClasses::class)
        ->searchTable('MATH 101')
        ->assertCanSeeTableRecords([$class]);
});

it('can search classes by subject title', function (): void {
    $subject = Subject::factory()->create([
        'code' => 'PATHFIT 2',
        'title' => 'Physical Activity Towards Health and Fitness 2',
    ]);

    $class = Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_code' => 'PATHFIT 2',
        'section' => 'C',
        'classification' => 'college',
    ]);

    // Search by subject title
    livewire(ListClasses::class)
        ->searchTable('Physical Activity')
        ->assertCanSeeTableRecords([$class]);
});

it('does not show wrong classes when searching', function (): void {
    // Create two different subjects
    $pathfit2 = Subject::factory()->create([
        'code' => 'PATHFIT 2',
        'title' => 'Physical Activity Towards Health and Fitness 2',
    ]);

    $pathfit4 = Subject::factory()->create([
        'code' => 'PATHFIT 4',
        'title' => 'Physical Activity Towards Health and Fitness 4',
    ]);

    // Create classes for each subject
    $classPathfit2 = Classes::factory()->create([
        'subject_id' => $pathfit2->id,
        'subject_code' => 'PATHFIT 2',
        'section' => 'A',
        'classification' => 'college',
    ]);

    $classPathfit4 = Classes::factory()->create([
        'subject_id' => $pathfit4->id,
        'subject_code' => 'PATHFIT 4',
        'section' => 'B',
        'classification' => 'college',
    ]);

    // Search for PATHFIT 2 should only show PATHFIT 2 class
    livewire(ListClasses::class)
        ->searchTable('PATHFIT 2')
        ->assertCanSeeTableRecords([$classPathfit2])
        ->assertCanNotSeeTableRecords([$classPathfit4]);

    // Search for PATHFIT 4 should only show PATHFIT 4 class
    livewire(ListClasses::class)
        ->searchTable('PATHFIT 4')
        ->assertCanSeeTableRecords([$classPathfit4])
        ->assertCanNotSeeTableRecords([$classPathfit2]);
});

it('can search classes by section', function (): void {
    $subject = Subject::factory()->create();

    $classA = Classes::factory()->create([
        'subject_id' => $subject->id,
        'section' => 'Section A',
        'classification' => 'college',
    ]);

    $classB = Classes::factory()->create([
        'subject_id' => $subject->id,
        'section' => 'Section B',
        'classification' => 'college',
    ]);

    // Search by section
    livewire(ListClasses::class)
        ->searchTable('Section A')
        ->assertCanSeeTableRecords([$classA])
        ->assertCanNotSeeTableRecords([$classB]);
});

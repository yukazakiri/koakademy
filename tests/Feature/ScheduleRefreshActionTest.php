<?php

declare(strict_types=1);

use App\Filament\Pages\Timetable;
use Illuminate\Support\Collection;

it('starts with the room view and no selected entity', function (): void {
    $page = new Timetable();

    expect($page->selectedView)->toBe('room')
        ->and($page->selectedId)->toBeNull()
        ->and($page->selectedYearLevel)->toBeNull();
});

it('exposes current filter state for the Filament form', function (): void {
    $page = new Timetable();
    $page->selectedView = 'course';
    $page->selectedId = '12';
    $page->selectedYearLevel = '3';

    expect($page->getFormData())->toBe([
        'selectedView' => 'course',
        'selectedId' => '12',
        'selectedYearLevel' => '3',
    ]);
});

it('loads an empty collection when no entity is selected', function (): void {
    $page = new Timetable();

    $page->loadSchedules();

    expect($page->schedules)->toBeInstanceOf(Collection::class)
        ->and($page->schedules)->toBeEmpty();
});

it('reports a clean conflict state before schedules are selected', function (): void {
    $page = new Timetable();

    expect($page->hasConflicts())->toBeFalse()
        ->and($page->getConflictCount())->toBe(0)
        ->and($page->getConflictSummaryText())->toBe('No conflicts detected.');
});

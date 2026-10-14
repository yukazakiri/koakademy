<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use HoceineEl\UndoToast\Livewire\UndoToast;
use HoceineEl\UndoToast\UndoToastPlugin;
use Livewire\Livewire;

it('registers undo toast plugin on admin and portal panels', function (): void {
    $adminPanel = Filament::getPanel('admin');
    $portalPanel = Filament::getPanel('portal');

    expect($adminPanel->hasPlugin('undo-toast'))->toBeTrue()
        ->and($portalPanel->hasPlugin('undo-toast'))->toBeTrue()
        ->and($adminPanel->getPlugin('undo-toast'))->toBeInstanceOf(UndoToastPlugin::class)
        ->and($portalPanel->getPlugin('undo-toast'))->toBeInstanceOf(UndoToastPlugin::class);
});

it('can render the undo toast livewire component', function (): void {
    Livewire::test(UndoToast::class)
        ->assertOk();
});

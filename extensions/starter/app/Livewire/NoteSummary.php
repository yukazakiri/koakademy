<?php

declare(strict_types=1);

namespace Koakademy\SchoolNotes\Livewire;

use Livewire\Component;

final class NoteSummary extends Component
{
    public int $requests = 0;

    public function increment(): void
    {
        $this->requests++;
    }

    public function render(): string
    {
        return '<div>School notes available</div>';
    }
}

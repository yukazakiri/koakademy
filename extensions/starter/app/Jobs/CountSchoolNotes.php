<?php

declare(strict_types=1);

namespace Koakademy\SchoolNotes\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Koakademy\SchoolNotes\Models\SchoolNote;

final class CountSchoolNotes implements ShouldQueue
{
    use Queueable;

    public int $tries = 30;

    public function __construct(public readonly int $schoolId) {}

    public function handle(): void
    {
        // Queue work carries its tenant explicitly; it never assumes an HTTP session.
        $count = SchoolNote::withoutSchoolScope()->forSchool($this->schoolId)->count();
        Cache::put('school-notes:count:'.$this->schoolId, $count, 300);
    }
}

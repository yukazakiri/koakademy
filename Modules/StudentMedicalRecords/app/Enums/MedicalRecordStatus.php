<?php

declare(strict_types=1);

namespace Modules\StudentMedicalRecords\Enums;

enum MedicalRecordStatus: string
{
    case Active = 'active';
    case Resolved = 'resolved';
    case Ongoing = 'ongoing';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Resolved => 'Resolved',
            self::Ongoing => 'Ongoing',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Resolved => 'primary',
            self::Ongoing => 'warning',
            self::Cancelled => 'danger',
        };
    }
}

<?php

declare(strict_types=1);

namespace Modules\StudentMedicalRecords\Enums;

enum MedicalRecordPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function getLabel(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Normal => 'Normal',
            self::High => 'High',
            self::Urgent => 'Urgent',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Normal => 'primary',
            self::High => 'warning',
            self::Urgent => 'danger',
        };
    }
}

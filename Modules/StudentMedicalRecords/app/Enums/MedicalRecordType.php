<?php

declare(strict_types=1);

namespace Modules\StudentMedicalRecords\Enums;

enum MedicalRecordType: string
{
    case Checkup = 'checkup';
    case Vaccination = 'vaccination';
    case Allergy = 'allergy';
    case Medication = 'medication';
    case Emergency = 'emergency';
    case Dental = 'dental';
    case Vision = 'vision';
    case MentalHealth = 'mental_health';
    case Laboratory = 'laboratory';
    case Surgery = 'surgery';
    case FollowUp = 'follow_up';

    public function getLabel(): string
    {
        return match ($this) {
            self::Checkup => 'General Checkup',
            self::Vaccination => 'Vaccination',
            self::Allergy => 'Allergy',
            self::Medication => 'Medication',
            self::Emergency => 'Emergency',
            self::Dental => 'Dental',
            self::Vision => 'Vision',
            self::MentalHealth => 'Mental Health',
            self::Laboratory => 'Laboratory Test',
            self::Surgery => 'Surgery',
            self::FollowUp => 'Follow Up',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Checkup => 'primary',
            self::Vaccination => 'success',
            self::Allergy => 'warning',
            self::Medication => 'info',
            self::Emergency => 'danger',
            self::Dental => 'secondary',
            self::Vision => 'primary',
            self::MentalHealth => 'purple',
            self::Laboratory => 'gray',
            self::Surgery => 'danger',
            self::FollowUp => 'warning',
        };
    }
}

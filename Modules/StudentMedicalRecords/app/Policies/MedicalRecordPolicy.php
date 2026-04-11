<?php

declare(strict_types=1);

namespace Modules\StudentMedicalRecords\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\StudentMedicalRecords\Models\MedicalRecord;
use Illuminate\Auth\Access\HandlesAuthorization;

class MedicalRecordPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MedicalRecord');
    }

    public function view(AuthUser $authUser, MedicalRecord $medicalRecord): bool
    {
        return $authUser->can('View:MedicalRecord');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MedicalRecord');
    }

    public function update(AuthUser $authUser, MedicalRecord $medicalRecord): bool
    {
        return $authUser->can('Update:MedicalRecord');
    }

    public function delete(AuthUser $authUser, MedicalRecord $medicalRecord): bool
    {
        return $authUser->can('Delete:MedicalRecord');
    }

    public function restore(AuthUser $authUser, MedicalRecord $medicalRecord): bool
    {
        return $authUser->can('Restore:MedicalRecord');
    }

    public function forceDelete(AuthUser $authUser, MedicalRecord $medicalRecord): bool
    {
        return $authUser->can('ForceDelete:MedicalRecord');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MedicalRecord');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MedicalRecord');
    }

    public function replicate(AuthUser $authUser, MedicalRecord $medicalRecord): bool
    {
        return $authUser->can('Replicate:MedicalRecord');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MedicalRecord');
    }

}
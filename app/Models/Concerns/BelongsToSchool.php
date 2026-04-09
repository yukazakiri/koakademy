<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\School;
use App\Models\Scopes\SchoolScope;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BelongsToSchool Trait
 *
 * This trait provides multi-tenancy functionality for models that belong to a school/organization.
 * It automatically:
 * - Scopes queries to the current tenant context
 * - Sets the school_id when creating new records
 * - Provides helper methods for school relationships and scoping
 *
 * Usage:
 * 1. Add 'school_id' column to the model's table
 * 2. Use this trait in the model: `use BelongsToSchool;`
 * 3. Add 'school_id' to $fillable if needed
 *
 * @property int|null $school_id
 * @property-read School|null $school
 *
 * @method static Builder<static> forSchool(School|int $school)
 * @method static Builder<static> withoutSchoolScope()
 */
trait BelongsToSchool
{
    /**
     * Boot the trait.
     */
    public static function bootBelongsToSchool(): void
    {
        // Add global scope for automatic filtering
        static::addGlobalScope(new SchoolScope);

        // Automatically set school_id when creating
        static::creating(function ($model): void {
            if (empty($model->school_id)) {
                try {
                    if (! app()->bound(\App\Services\TenantContext::class)) {
                        return;
                    }

                    $tenantContext = app(\App\Services\TenantContext::class);
                    $schoolId = $tenantContext->getCurrentSchoolId();

                    if ($schoolId) {
                        $model->school_id = $schoolId;
                    }
                } catch (BindingResolutionException) {
                    // Application not ready, skip auto-setting school_id
                }
            }
        });
    }

    /**
     * Get the school that this model belongs to.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class, 'school_id', 'id');
    }

    /**
     * Scope query to a specific school.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForSchool(Builder $query, School|int $school): Builder
    {
        $schoolId = $school instanceof School ? $school->id : $school;

        return $query->where($this->getTable().'.school_id', $schoolId);
    }

    /**
     * Scope query to exclude school filtering.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithoutSchoolScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(SchoolScope::class);
    }

    /**
     * Check if this model belongs to a specific school.
     */
    public function belongsToSchool(School|int $school): bool
    {
        $schoolId = $school instanceof School ? $school->id : $school;

        return $this->school_id === $schoolId;
    }

    /**
     * Check if this model belongs to the current tenant.
     */
    public function belongsToCurrentSchool(): bool
    {
        try {
            if (! app()->bound(\App\Services\TenantContext::class)) {
                return true; // No tenant context means global access
            }

            $tenantContext = app(\App\Services\TenantContext::class);
            $currentSchoolId = $tenantContext->getCurrentSchoolId();

            if ($currentSchoolId === null) {
                return true; // No tenant context means global access
            }

            return $this->school_id === $currentSchoolId;
        } catch (BindingResolutionException) {
            return true; // Application not ready, assume global access
        }
    }

    /**
     * Get the column name for school foreign key.
     */
    public function getSchoolForeignKeyName(): string
    {
        return 'school_id';
    }
}

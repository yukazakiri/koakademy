<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Services\TenantContext;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * SchoolScope - Global scope for multi-tenancy filtering.
 *
 * This scope automatically filters queries to only return records
 * belonging to the current tenant (school) context.
 *
 * The scope is bypassed when:
 * - No tenant context is set
 * - The current user can access all organizations (super admin)
 * - The query explicitly removes the scope with withoutSchoolScope()
 * - The application is not fully booted (during tests/console commands)
 */
final class SchoolScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Guard: Skip if application is not fully booted
        // This prevents errors during testing and console commands
        try {
            if (! app()->bound(TenantContext::class)) {
                return;
            }

            $tenantContext = app(TenantContext::class);
        } catch (BindingResolutionException) {
            // Application not ready, skip scoping
            return;
        }

        $schoolId = $tenantContext->getCurrentSchoolId();

        // Only apply scope if we have a current school context
        if ($schoolId !== null) {
            $builder->where($model->getTable().'.school_id', $schoolId);
        }
    }
}

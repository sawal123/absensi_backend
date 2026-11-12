<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantId = TenantContext::id();
            if ($tenantId === null) {
                return;
            }

            $builder->where(
                $builder->getModel()->getTable().'.tenant_id',
                $tenantId,
            );
        });

        static::creating(function (Model $model) {
            if ($model->getAttribute('tenant_id') !== null) {
                return;
            }

            // Super admin platform tidak terikat tenant.
            if (
                $model instanceof User
                && $model->getAttribute('role') === User::ROLE_SUPER_ADMIN
            ) {
                return;
            }

            $tenantId = TenantContext::id();
            if ($tenantId !== null) {
                $model->setAttribute('tenant_id', $tenantId);
            }
        });
    }
}

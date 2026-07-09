<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Scopes queries to the current company (session for super admin, user.company_id otherwise).
 * When company context is missing, no scope is applied (e.g. Artisan without auth).
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            $cid = current_company_id();
            if ($cid === null) {
                return;
            }
            $builder->where($builder->getModel()->getTable().'.company_id', $cid);
        });

        static::creating(function (Model $model) {
            if ($model->getAttribute('company_id') !== null) {
                return;
            }
            $cid = current_company_id();
            if ($cid !== null) {
                $model->setAttribute('company_id', $cid);
            }
        });
    }
}

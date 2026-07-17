<?php

namespace App\Services\Sync;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Mass Query Builder deletes skip Eloquent events — cloud sync then keeps orphans.
 * Use these helpers so each row fires deleted → sync_queue.
 */
final class SyncAwareDelete
{
    public static function models(iterable $models): int
    {
        $count = 0;
        foreach ($models as $model) {
            if ($model instanceof Model) {
                $model->delete();
                $count++;
            }
        }

        return $count;
    }

    public static function relation(Relation $relation): int
    {
        return self::models($relation->get());
    }

    public static function query(Builder $query): int
    {
        return self::models($query->get());
    }
}

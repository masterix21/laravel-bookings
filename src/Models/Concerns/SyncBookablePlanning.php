<?php

namespace Masterix21\Bookings\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/** @mixin Model */
trait SyncBookablePlanning
{
    public static function bootSyncBookablePlanning(): void
    {
        static::saved(static function (BookablePlanningSource $model) {
            $model->syncBookablePlanning();
        });
    }
}

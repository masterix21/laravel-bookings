<?php

namespace Masterix21\Bookings\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/** @mixin Model */
trait SyncBookableResource
{
    public static function bootSyncBookableResource(): void
    {
        static::saved(static function (Bookable $model) {
            if (! $model->relationLoaded('bookableResources')) {
                $model->load('bookableResources');
            }

            $model->bookableResources->each(function ($resource) use ($model) {
                $model->syncBookableResource($resource);
            });
        });
    }
}

<?php

declare(strict_types=1);

namespace Masterix21\Bookings\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/** @mixin Model */
trait SyncBookableResource
{
    public static function bootSyncBookableResource(): void
    {
        static::saved(static function (Bookable $model) {
            if (method_exists($model, 'bookableResource') && ! method_exists($model, 'bookableResources')) {
                if (! $model->relationLoaded('bookableResource')) {
                    $model->load('bookableResource');
                }

                if ($model->bookableResource) {
                    $model->syncBookableResource($model->bookableResource);
                }
            } else {
                if (! $model->relationLoaded('bookableResources')) {
                    $model->load('bookableResources');
                }

                $model->bookableResources->each(function ($resource) use ($model) {
                    $model->syncBookableResource($resource);
                });
            }
        });
    }
}

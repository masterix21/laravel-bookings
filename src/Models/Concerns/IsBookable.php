<?php

namespace Masterix21\Bookings\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Masterix21\Bookings\Models\BookableResource;

/** @mixin Model */
trait IsBookable
{
    use ImplementsEnsureIsAvailable;
    use UsesBookablePlannings;

    public static function bootIsBookable(): void
    {
        static::deleting(function (Bookable $model) {
            $model->bookableResource()->delete();
        });
    }

    public function bookableResource(): MorphOne
    {
        return $this->morphOne(BookableResource::class, 'model');
    }
}

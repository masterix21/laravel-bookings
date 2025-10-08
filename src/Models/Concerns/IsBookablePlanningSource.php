<?php

namespace Masterix21\Bookings\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/** @mixin Model */
trait IsBookablePlanningSource
{
    public static function bootIsBookablePlanningSource(): void
    {
        static::deleting(static function (BookablePlanningSource $model) {
            $model->planning()->delete();
        });
    }

    public function planning(): MorphOne
    {
        return $this->morphOne(config('bookings.models.bookable_planning'), 'source')->chaperone();
    }
}

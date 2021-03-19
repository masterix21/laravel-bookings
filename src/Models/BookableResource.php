<?php

namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Masterix21\Bookings\Models\Concerns\BelongsToBookableArea;
use Masterix21\Bookings\Models\Concerns\HasBookedPeriods;

class BookableResource extends Model
{
    use HasFactory;
    use BelongsToBookableArea;
    use HasBookedPeriods;

    protected $guarded = [];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function bookablePlannings(): HasMany
    {
        return $this->hasMany(config('bookings.models.bookable_planning'));
    }
}

<?php

namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Masterix21\Bookings\Models\Concerns\BelongsToArea;
use Masterix21\Bookings\Models\Concerns\BelongsToResource;

class Booking extends Model
{
    use HasFactory;
    use BelongsToArea;
    use BelongsToResource;

    public function children(): HasMany
    {
        return $this->hasMany(config('bookings.models.booking_child'));
    }

    public function boundaries(): HasMany
    {
        return $this->hasMany(config('bookings.models.period'));
    }

    public function exclusions(): HasManyThrough
    {
        return $this->hasManyThrough(config('bookings.models.exclusion'), config('bookings.models.period'));
    }
}

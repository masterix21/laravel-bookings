<?php

namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Masterix21\Bookings\Models\Concerns\BelongsToArea;
use Masterix21\Bookings\Models\Concerns\HasTimetables;

class Resource extends Model
{
    use HasFactory,
        BelongsToArea,
        HasTimetables;

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function children(): HasMany
    {
        return $this->hasMany(config('bookings.models.resource_child'));
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(config('bookings.models.booking'));
    }
}

<?php

namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Znck\Eloquent\Relations\BelongsToThrough;
use Znck\Eloquent\Traits\BelongsToThrough as HasBelongsToThrough;

class Exclusion extends Model
{
    use HasFactory;
    use HasBelongsToThrough;

    public function booking(): belongsToThrough
    {
        return $this->belongsToThrough(config('bookings.models.booking'), config('bookings.models.period'));
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(config('bookings.models.period'));
    }
}

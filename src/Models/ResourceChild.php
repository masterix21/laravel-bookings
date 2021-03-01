<?php

namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Masterix21\Bookings\Models\Concerns\BelongsToArea;
use Masterix21\Bookings\Models\Concerns\BelongsToResource;

class ResourceChild extends Model
{
    use HasFactory;
    use BelongsToArea;
    use BelongsToResource;

    public function parent(): BelongsTo
    {
        return $this->belongsTo(config('bookings.models.resource'));
    }
}

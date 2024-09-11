<?php

namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kirschbaum\PowerJoins\PowerJoins;
use Masterix21\Bookings\Models\Concerns\Relationships\BelongsToBookableArea;
use Masterix21\Bookings\Models\Concerns\Relationships\BelongsToBookableResource;
use Masterix21\Bookings\Models\Concerns\Relationships\BelongsToBooking;
use Masterix21\Bookings\Models\Concerns\Relationships\HasBookedPeriods;
use Masterix21\Bookings\Models\Concerns\Relationships\HasParentAndChildren;
use Masterix21\Bookings\Models\Concerns\UsesAddBookedResources;
use Masterix21\Bookings\Models\Concerns\UsesBookedPeriods;
use Masterix21\Bookings\Models\Concerns\UsesGenerateBookedPeriods;

class BookedResource extends Model
{
    use HasFactory;
    use PowerJoins;
    use BelongsToBooking;
    use HasParentAndChildren;
    use BelongsToBookableArea;
    use BelongsToBookableResource;
    use HasBookedPeriods;
    use UsesAddBookedResources;
    use UsesGenerateBookedPeriods;
    use UsesBookedPeriods;

    protected $guarded = [];
}

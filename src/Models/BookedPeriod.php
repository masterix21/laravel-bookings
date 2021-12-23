<?php
namespace Masterix21\Bookings\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Kirschbaum\PowerJoins\PowerJoins;
use Masterix21\Bookings\Models\Concerns\Relationships\BelongsToBookableArea;
use Masterix21\Bookings\Models\Concerns\Relationships\BelongsToBookableResource;
use Masterix21\Bookings\Models\Concerns\Relationships\BelongsToBookedResource;
use Masterix21\Bookings\Models\Concerns\Relationships\BelongsToBooking;
use Masterix21\Bookings\Models\Concerns\Scopes\HasWherePeriodFromDatesScope;

class BookedPeriod extends Model
{
    use HasFactory;
    use PowerJoins;
    use BelongsToBooking;
    use BelongsToBookableArea;
    use BelongsToBookableResource;
    use BelongsToBookedResource;
    use HasWherePeriodFromDatesScope;

    protected $guarded = [];
}

<?php

namespace Masterix21\Bookings\Models\Concerns;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Masterix21\Bookings\Actions\BookResource;
use Masterix21\Bookings\Models\Booking;
use Spatie\Period\PeriodCollection;

trait ImplementsBook
{
    public function book(
        PeriodCollection $periods,
        ?Booking $booking = null,
        ?Model $booker = null,
        Collection|EloquentCollection|null $relations = null,
        ?string $code = null,
        ?string $label = null,
        ?string $note = null,
    ): ?Booking {
        return (new BookResource)->run(
            booker: $booker,
            periods: $periods,
            bookableResource: $this,
            booking: $booking,
            creator: auth()->user(),
            code: $code,
            label: $label,
            note: $note
        );
    }
}

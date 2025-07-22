<?php

namespace Masterix21\Bookings\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Masterix21\Bookings\Models\BookableResource;
use Spatie\Period\PeriodCollection;

class BookingInProgress
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public BookableResource $bookableResource,
        public PeriodCollection $periods,
    ) {
    }
}

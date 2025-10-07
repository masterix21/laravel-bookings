<?php

namespace Masterix21\Bookings\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Masterix21\Bookings\Enums\UnbookableReason;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\Booking;
use Spatie\Period\PeriodCollection;

class BookingChangeFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Booking $booking,
        public UnbookableReason $reason,
        public BookableResource $bookableResource,
        public PeriodCollection $periods,
        public ?string $message = null,
        public ?string $stackTrace = null,
    ) {}
}

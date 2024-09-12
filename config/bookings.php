<?php

return [
    'models' => [
        'user' => \Illuminate\Foundation\Auth\User::class,
        'bookable_area' => \Masterix21\Bookings\Models\BookableArea::class,
        'bookable_resource' => \Masterix21\Bookings\Models\BookableResource::class,
        'bookable_planning' => \Masterix21\Bookings\Models\BookablePlanning::class,
        'bookable_relation' => \Masterix21\Bookings\Models\BookableRelation::class,
        'booking' => \Masterix21\Bookings\Models\Booking::class,
        'booked_period' => \Masterix21\Bookings\Models\BookedPeriod::class,
    ],
];

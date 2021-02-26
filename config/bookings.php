<?php

return [
    /*
     * User model
     */
    'user' => \Illuminate\Foundation\Auth\User::class,

    'models' => [
        'area' => \Masterix21\Bookings\Models\Area::class,
        'resource' => \Masterix21\Bookings\Models\Resource::class,
        'timetable' => \Masterix21\Bookings\Models\Timetable::class,
        'resource_child' => \Masterix21\Bookings\Models\ResourceChild::class,
        'booking' => \Masterix21\Bookings\Models\Booking::class,
        'boundary' => \Masterix21\Bookings\Models\Boundary::class,
        'exclusion' => \Masterix21\Bookings\Models\Exclusion::class,
        'booking_child' => \Masterix21\Bookings\Models\BookingChild::class,
    ],
];

<?php

use Illuminate\Foundation\Auth\User;
use Masterix21\Bookings\Generators\RandomBookingCode;
use Masterix21\Bookings\Models\BookablePlanning;
use Masterix21\Bookings\Models\BookableRelation;
use Masterix21\Bookings\Models\BookableResource;
use Masterix21\Bookings\Models\BookedPeriod;
use Masterix21\Bookings\Models\Booking;

return [
    'models' => [
        'user' => User::class,
        'bookable_resource' => BookableResource::class,
        'bookable_planning' => BookablePlanning::class,
        'bookable_relation' => BookableRelation::class,
        'booking' => Booking::class,
        'booked_period' => BookedPeriod::class,
    ],

    'generators' => [
        'booking_code' => RandomBookingCode::class,
    ],

    'planning_validation' => [
        'batch_size' => 100,
    ],

    'booking_update' => [
        'preserve_deleted_periods' => false,
    ],
];

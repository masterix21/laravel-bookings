<?php

namespace Masterix21\Bookings;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class BookingsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-bookings')
            ->hasConfigFile()
            ->hasMigrations([
                "create_booking_areas_table",
                "create_booking_resources_table",
                "create_booking_timetables_table",
                "create_booking_resource_children_table",
                "create_bookings_table",
                "create_booking_boundaries_table",
                "create_booking_exclusions_table",
                "create_booking_children_table",
            ]);
    }
}

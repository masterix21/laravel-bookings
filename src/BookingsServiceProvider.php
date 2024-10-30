<?php

namespace Masterix21\Bookings;

use Masterix21\Bookings\Generators\Contracts\BookingCodeGenerator;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class BookingsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-bookings')
            ->hasConfigFile()
            ->hasMigrations([
                'create_bookable_areas_table',
                'create_bookable_resources_table',
                'create_bookable_plannings_table',
                'create_bookable_relations_table',
                'create_bookings_table',
                'create_booked_resources_table',
                'create_booked_periods_table',
            ]);
    }

    public function packageBooted(): void
    {
        $this->app->bind(BookingCodeGenerator::class, config('bookings.generators.booking_code'));

        $this->app->singleton('bookings', Bookings::class);
    }
}

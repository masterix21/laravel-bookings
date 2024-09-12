<?php

namespace Masterix21\Bookings;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class BookingsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider.
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
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

    public function packageBooted()
    {
        $this->app->singleton('bookings', Bookings::class);
    }
}

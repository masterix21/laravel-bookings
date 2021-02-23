<?php

namespace Masterix21\Bookings;

use Masterix21\Bookings\Commands\BookingsCommand;
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
            ->hasViews()
            ->hasMigration('create_booking_resources_table')
            ->hasMigration('create_booking_resource_parent_table')
            ->hasMigration('create_bookings_table')
            ->hasCommand(BookingsCommand::class);
    }
}

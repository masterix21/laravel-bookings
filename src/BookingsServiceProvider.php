<?php

namespace LucaLongo\Bookings;

use LucaLongo\Bookings\Commands\BookingsCommand;
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
            ->hasMigrations(['booking_resources', 'booking_resource_parent', 'bookings'])
            ->hasCommand(BookingsCommand::class);
    }
}

# Add bookings ability to any Eloquent model

[![Latest Version on Packagist](https://img.shields.io/packagist/v/masterix21/laravel-bookings.svg?style=flat-square)](https://packagist.org/packages/masterix21/laravel-bookings)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/masterix21/laravel-bookings/run-tests?label=tests)](https://github.com/masterix21/laravel-bookings/actions?query=workflow%3ATests+branch%3Amaster)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/masterix21/laravel-bookings/Check%20&%20fix%20styling?label=code%20style)](https://github.com/masterix21/laravel-bookings/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amaster)
[![Total Downloads](https://img.shields.io/packagist/dt/masterix21/laravel-bookings.svg?style=flat-square)](https://packagist.org/packages/masterix21/laravel-bookings)

Fresh way to concepts the booking processes: for any eloquent model, for any app. Highly customizable.

## Installation

You can install the package via composer:

```bash
composer require masterix21/laravel-bookings
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --provider="Masterix21\Bookings\BookingsServiceProvider" --tag="laravel-bookings-migrations"
php artisan migrate
```

You can publish the config file with:
```bash
php artisan vendor:publish --provider="Masterix21\Bookings\BookingsServiceProvider" --tag="laravel-bookings-config"
```

## Usage

@TODO

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Luca Longo](https://github.com/masterix21)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

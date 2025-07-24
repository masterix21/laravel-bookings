# Contributing to Laravel Bookings

Thank you for considering contributing to Laravel Bookings! This document provides guidelines and information about contributing to this project.

## Code of Conduct

This project adheres to a code of conduct. By participating, you are expected to uphold this code. Please be respectful and constructive in all interactions.

## How Can I Contribute?

### Reporting Bugs

Before creating bug reports, please check the existing issues to avoid duplicates. When creating a bug report, include:

- **Clear description** of the issue
- **Steps to reproduce** the behavior
- **Expected behavior** vs actual behavior
- **Environment details** (PHP version, Laravel version, package version)
- **Code samples** that demonstrate the issue
- **Stack traces** or error messages if applicable

### Suggesting Enhancements

Enhancement suggestions are welcome! Please:

- Check existing issues and discussions first
- Provide a clear description of the enhancement
- Explain why this enhancement would be useful
- Include examples of how it would work
- Consider backward compatibility

### Pull Requests

1. **Fork** the repository
2. **Create a feature branch** from `master`: `git checkout -b feature/amazing-feature`
3. **Make your changes** following our coding standards
4. **Add or update tests** as needed
5. **Update documentation** if required
6. **Run the test suite** to ensure nothing breaks
7. **Commit your changes** with clear commit messages
8. **Push to your branch**: `git push origin feature/amazing-feature`
9. **Open a Pull Request** with a clear title and description

## Development Setup

### Prerequisites

- PHP 8.3 or higher
- Composer
- Laravel 12.0 or higher

### Getting Started

1. **Clone your fork:**
   ```bash
   git clone https://github.com/your-username/laravel-bookings.git
   cd laravel-bookings
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Run tests to ensure everything works:**
   ```bash
   composer test
   ```

### Development Commands

- **Run tests:** `composer test`
- **Run tests with coverage:** `composer test-coverage`
- **Run static analysis:** `composer analyse`
- **Format code:** `composer format`
- **Build package:** `composer build`
- **Start development server:** `composer start`

### Testing

We use [Pest](https://pestphp.com/) for testing. Please ensure:

- **All tests pass** before submitting PR
- **New features have tests** covering the functionality
- **Bug fixes include regression tests**
- **Test coverage remains high**

Run specific tests:
```bash
# Run all tests
vendor/bin/pest

# Run specific test file
vendor/bin/pest tests/Feature/Actions/BookResourceTest.php

# Run specific test
vendor/bin/pest --filter="test_name"

# Run with coverage
vendor/bin/pest --coverage
```

## Coding Standards

This project follows the [Laravel PHP Guidelines](laravel-php-guidelines.md) and [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standards.

### Key Guidelines

- **Follow Laravel conventions** first
- **Use typed properties** over docblocks
- **Prefer early returns** over nested conditions
- **Use constructor property promotion** when possible
- **Always use curly braces** for control structures
- **Use string interpolation** over concatenation

### Code Style

We use [Laravel Pint](https://laravel.com/docs/pint) for code formatting:

```bash
composer format
```

### Static Analysis

We use [PHPStan](https://phpstan.org/) for static analysis:

```bash
composer analyse
```

## Documentation

When contributing, please update documentation as needed:

- **Update README.md** for new features or changes
- **Update CHANGELOG.md** following [Keep a Changelog](https://keepachangelog.com/) format
- **Add PHPDoc blocks** for new public methods
- **Update configuration documentation** if config changes

## Architecture Guidelines

### Models and Traits

- **Models** should be focused and follow single responsibility
- **Traits** should be composable and well-documented
- **Use polymorphic relationships** appropriately
- **Follow Eloquent conventions** for relationships

### Actions

- **Actions** should be single-purpose classes
- **Use database transactions** for complex operations
- **Emit events** for important lifecycle changes
- **Handle exceptions** gracefully

### Events

- **Events** should be descriptive and carry necessary data
- **Use past tense** for completed actions (`BookingCompleted`)
- **Use present continuous** for ongoing actions (`BookingInProgress`)

### Tests

- **Feature tests** for end-to-end scenarios
- **Unit tests** for isolated components
- **Use factories** for test data
- **Follow arrange-act-assert** pattern

## Database Guidelines

### Migrations

- **Use descriptive names** for migrations
- **Only use `up()` methods** (no down methods)
- **Test migrations** with fresh database
- **Consider backward compatibility**

### Schema Design

- **Use appropriate column types**
- **Add proper indexes** for performance
- **Use foreign key constraints** where appropriate
- **Consider nullable columns** carefully

## Performance Considerations

- **Use eager loading** to prevent N+1 queries
- **Add database indexes** for commonly queried columns
- **Optimize complex queries** with proper joins
- **Consider caching** for expensive operations

## Security Guidelines

- **Validate all inputs** properly
- **Use parameter binding** for database queries
- **Follow Laravel security practices**
- **Don't expose sensitive data** in logs or responses

## Version Compatibility

- **Maintain backward compatibility** within major versions
- **Document breaking changes** in CHANGELOG
- **Follow semantic versioning** (SemVer)
- **Test against supported PHP/Laravel versions**

## Getting Help

- **Check existing issues** and documentation first
- **Open a discussion** for questions about usage
- **Open an issue** for bugs or feature requests
- **Be patient and respectful** when asking for help

## Recognition

Contributors will be recognized in:
- The CHANGELOG.md file
- The README.md credits section
- GitHub contributor statistics

## Questions?

If you have questions about contributing, please:

1. Check the existing documentation
2. Search existing issues and discussions
3. Open a new discussion or issue
4. Be specific about what you need help with

Thank you for contributing to Laravel Bookings! 🚀

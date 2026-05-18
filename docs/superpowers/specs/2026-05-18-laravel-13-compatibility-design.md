# Laravel 13 Compatibility — Design

Date: 2026-05-18
Status: Approved

## Goal

Make `masterix21/laravel-bookings` compatible with **Laravel 13** while keeping
**Laravel 12** support (dual support). The CI suite must run green on both
Laravel versions across `prefer-lowest` and `prefer-stable`.

## Context

Current state:

- `composer.json` requires `illuminate/contracts: ^12.0`, dev `orchestra/testbench: ^10.0.0`, PHP `^8.4`.
- CI (`run-tests.yml`) tests PHP 8.4 × Laravel `12.*` × testbench `10.*` × `prefer-lowest`/`prefer-stable` on Ubuntu + Windows.

Dependency audit (all already permit Laravel 13 resolution with current constraints):

| Dependency | Current constraint | Laravel 13 support |
|---|---|---|
| `spatie/laravel-package-tools` | `^1.92` | 1.93.0 adds `^13.0` |
| `kirschbaum-development/eloquent-power-joins` | `^4.2` | 4.3.1 adds `^13.0` |
| `staudenmeir/belongs-to-through` | `^2.16` | v2.18 targets `^13.0` (per-version releases) |
| `staudenmeir/eloquent-has-many-deep` | `^1.20` | v1.22.1 targets `^13.0` |
| `spatie/period` | `^2.4` | framework-agnostic |

No dependency is a blocker. The work is configuration/build plus verification.

## Decisions

- **Scope:** dual support — Laravel 12 + 13.
- **Dependencies:** widen constraints and let Composer resolve; address case by
  case only if a resolution actually fails under test.

## Changes

### 1. `composer.json`

- `illuminate/contracts`: `^12.0` → `^12.0 || ^13.0`
- `orchestra/testbench` (require-dev): `^10.0.0` → `^10.0 || ^11.0`
- Bump the *minimum* of the four Laravel-bound dependencies **only if**
  `prefer-lowest` on Laravel 13 fails to resolve — driven by test results, not a priori.
- `larastan/larastan`: verify Laravel 13 support; bump if required.

### 2. CI — `.github/workflows/run-tests.yml`

Extend the matrix to add a Laravel 13 row, keeping the existing Laravel 12 row:

- `laravel: 13.*` paired with `testbench: 11.*`
- Keep PHP 8.4, Ubuntu + Windows, `prefer-lowest` / `prefer-stable`.

### 3. CI — `.github/workflows/phpstan.yml`

Leave the workflow as-is. PHPStan must still pass with the updated dependency
set; no dedicated Laravel-13 PHPStan matrix is added (kept simple).

### 4. Local verification

- Install Laravel 13 + testbench 11 locally.
- Run `composer test` and `composer analyse`.
- Fix any Laravel 13 breaking changes surfaced in the package source.
- Confirm no regression on Laravel 12.

### 5. Documentation

- Update `README.md` requirements/badges to mention Laravel 12 & 13.
- Update `CHANGELOG.md` with the compatibility entry.

## Success criteria

- Pest suite green on Laravel 12 **and** Laravel 13 (both stabilities).
- PHPStan green.
- No regression on Laravel 12.

## Out of scope

- No functional changes to booking behavior.
- No drop of Laravel 12 support.
- No PHP version range change (stays `^8.4`).

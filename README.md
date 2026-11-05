# Laravix

Indian GST accounting backend. Step 1 targets PHP 8.3, Laravel 11, MySQL 8,
Livewire 3 (including Alpine), Tailwind 3, Redis, Pest, Spatie permission and
activitylog, DomPDF via its Laravel integration, and Sanctum. No application UI yet.

## Setup

Install PHP 8.3 with BCMath, PDO MySQL, mbstring, XML, curl, zip and fileinfo.
Redis connections use the phpredis extension (no additional Composer client).

```sh
composer install
cp .env.example .env
php artisan key:generate
# Set MySQL and Redis credentials in .env.
php artisan migrate --seed
npm ci
npm run build
php vendor/pestphp/pest/bin/pest
php artisan queue:work redis
```

Tests default to isolated in-memory SQLite. MySQL integration tests are also
required before deployment for transaction locks and production constraints.
Never run tests against production data.

## Dependency status

Laravel 11 was explicitly requested. Its security support ended on March 12, 2026:
<https://laravel.com/docs/11.x/releases>. Composer currently reports advisories
against Laravel 11. Initial dependency resolution required a one-time
`--no-security-blocking` override; project security policy is not disabled.
Run `composer audit` and upgrade the framework before exposing this app publicly.
The lockfile preserves the dependency set, not a claim of security clearance.

Only requested direct packages are installed. Pest and framework dependencies are
transitive. Alpine ships with Livewire; Tailwind's CLI handles the placeholder CSS.
There is no separate Alpine bootstrap, starter UI, or extra Redis client package.

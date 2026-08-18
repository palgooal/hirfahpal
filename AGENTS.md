# Repository Guidelines

## Project Structure & Module Organization
This is a Laravel 11 application. Core PHP code lives in `app/`, with controllers under `app/Http/Controllers`, form requests under `app/Http/Requests`, models under `app/Models`, and shared support code under `app/Support`. HTTP routes are split across `routes/web.php`, `routes/dashboard.php`, and `routes/owner.php`. Blade views and frontend entrypoints are in `resources/`; Vite builds `resources/css/app.css` and `resources/js/app.js`. Database migrations, factories, and seeders are in `database/`. Public assets are served from `public/`, while `ask/` contains static prototype pages and design notes. Tests are organized into `tests/Feature` and `tests/Unit`.

## Build, Test, and Development Commands
- `composer install`: install PHP dependencies.
- `npm install`: install Vite, Tailwind, and frontend dependencies.
- `php artisan serve`: run the Laravel development server.
- `npm run dev`: start Vite with hot reload.
- `npm run build`: create production frontend assets.
- `php artisan migrate --seed`: run migrations and seed local data.
- `php artisan test`: run the PHPUnit test suite.
- `./vendor/bin/pint`: format PHP code with Laravel Pint.

## Coding Style & Naming Conventions
Follow `.editorconfig`: UTF-8, LF line endings, final newline, trimmed trailing whitespace, 4-space indentation, and 2 spaces for YAML. Use PSR-4 namespaces under `App\`. Name controllers, models, requests, and middleware with standard Laravel suffixes such as `BusinessController`, `SearchRequest`, and `EnsureUserIsAdmin`. Keep migrations timestamped and snake_case. Place Blade views by feature, for example `resources/views/dashboard/settings/index.blade.php`.

## Testing Guidelines
Tests use PHPUnit 10 through Laravel's test runner. Prefer feature tests for routes, guards, dashboards, search behavior, file uploads, and database persistence. Use `RefreshDatabase` where tests write data. Keep unit tests for isolated service or helper logic. Match the existing descriptive `test_*` method style, such as `test_guest_cannot_access_settings`.

## Commit & Pull Request Guidelines
Recent commits are informal and terse, for example `job`, `business`, and `login owner`. For new work, use short imperative subjects such as `Add owner login validation`. Pull requests should explain the purpose, list test commands run, link related issues when available, include screenshots for UI or dashboard changes, and call out migrations, seeders, or configuration changes.

## Security & Configuration Tips
Keep `.env` local and never commit secrets, logs, generated uploads, or private keys. Review storage and media changes carefully before committing. Composer includes a local path repository at `../Palgoal-Laravel-Media-Library`; ensure that dependency is available when installing packages locally.

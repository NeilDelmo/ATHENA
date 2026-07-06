# Repository structure

This branch adapts ATHENA to a generic `src/tests/docs/assets` repository layout while retaining Laravel's runtime entry points.

## Layout

- `src/app/` contains PHP application code.
- `src/bootstrap/` contains the Laravel bootstrap and provider manifest.
- `src/config/` contains runtime configuration definitions.
- `src/database/` contains migrations, factories, and seeders.
- `src/resources/` contains Blade views, JavaScript, CSS, and uncompiled assets.
- `src/routes/` contains application route definitions.
- `tests/` contains Pest and PHPUnit tests.
- `docs/` contains project reports and technical documentation.
- `assets/` contains design references and non-runtime images or media.
- `public/` remains the web-server document root.
- `storage/` remains outside source control as runtime state.

## Laravel path customizations

Because Laravel normally expects these directories at the root, this repository updates:

- Composer PSR-4 paths to load `App` and database namespaces from `src/`.
- Root `artisan` and `public/index.php` to load `src/bootstrap/app.php`.
- The application base path to `src/`, while keeping `.env`, `public/`, and `storage/` at the project root.
- Vite and Tailwind inputs to scan `src/resources/`.
- PHPUnit source coverage to scan `src/app/` while keeping test suites in `tests/`.

The root remains the working directory for Composer, npm, Artisan, and Git commands.

## Dependency files

ATHENA does not use Python, so a `requirements.txt` file is intentionally omitted.

- `composer.json` and `composer.lock` define reproducible PHP dependencies.
- `package.json` and `package-lock.json` define reproducible frontend dependencies.

This organization is framework-aware but more customized than a conventional Laravel repository. Any Laravel upgrade should preserve and retest these path overrides.

# Repository structure

ATHENA intentionally uses Laravel's conventional project structure. The folders at the repository root are framework entry points, not unorganized files.

## Why there is no `src/` wrapper

Generic repository examples often place application code in `src/`. Laravel already defines more specific source directories:

- `app/` contains PHP application code.
- `resources/` contains Blade, JavaScript, CSS, and uncompiled assets.
- `routes/` contains application route definitions.
- `database/` contains migrations, factories, and seeders.
- `config/` contains runtime configuration definitions.
- `public/` contains the web entry point and public assets.

Moving these directories beneath `src/` would require custom changes to Composer autoloading, Artisan bootstrap paths, Vite inputs, PHPUnit paths, the web server document root, and deployment instructions. Keeping the standard layout makes the project easier for Laravel developers and hosting platforms to reuse.

## Dependency files

ATHENA does not use Python, so a `requirements.txt` file is not included.

- `composer.json` and `composer.lock` define reproducible PHP dependencies.
- `package.json` and `package-lock.json` define reproducible frontend dependencies.

## Tests, documentation, and assets

- Automated tests live in the root `tests/` directory, as expected by Pest and PHPUnit.
- Project reports and technical documents live in `docs/`.
- Public browser assets belong in `public/`.
- Source assets processed by Vite belong in `resources/`.

This layout provides the same separation intended by a generic `src/tests/docs/assets` template while preserving Laravel compatibility.

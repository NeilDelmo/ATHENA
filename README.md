# ATHENA

**Automated Research Management and Monitoring System with Analytics and Research Support Tools**

ATHENA is a Laravel-based research management portal developed for Batangas State University. It centralizes faculty proposal submission, document versioning, research review workflows, notifications, and AI-assisted research support.

## Features

- Role-based workspaces for faculty, faculty researchers, research heads, and expert evaluators
- Google account authentication with institutional-domain restrictions
- Research call and proposal-template management
- Multi-document proposal packages with revision history
- Initial screening, expert assignment, comments, and approval workflows
- Database and real-time notifications through Laravel Reverb
- Groq-powered research assistant restricted to faculty researchers
- Responsive interface with light and dark themes

## Technology stack

- PHP 8.3 and Laravel 13
- MySQL
- Blade, Alpine.js, Tailwind CSS, and Vite
- Laravel Reverb and Echo
- Pest for automated testing
- Groq API using `openai/gpt-oss-120b` by default

## Requirements

- PHP 8.3 or newer with the extensions required by Laravel
- Composer
- Node.js and npm
- MySQL

ATHENA is not a Python application, so it does not use `requirements.txt`. PHP dependencies are declared in `composer.json`, while frontend dependencies are declared in `package.json`.

## Local installation

1. Clone the repository and enter its directory.

   ```bash
   git clone https://github.com/NeilDelmo/ATHENA.git
   cd ATHENA
   ```

2. Install PHP and frontend dependencies.

   ```bash
   composer install
   npm install
   ```

3. Create the local environment file and application key.

   On PowerShell:

   ```powershell
   Copy-Item .env.example .env
   php artisan key:generate
   ```

   On macOS or Linux:

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. Configure the database, Google authentication, Reverb, and optional Groq credentials in `.env`.

5. Create the database tables and initial roles/data.

   ```bash
   php artisan migrate --seed
   ```

6. Start the development services.

   ```bash
   composer run dev
   ```

The `dev` command starts Laravel, the queue worker, application logs, Reverb, and Vite together.

## AI research assistant

To enable the faculty-researcher chatbot, create a Groq API key and configure:

```env
AI_PROVIDER=groq
GROQ_API_KEY=
GROQ_MODEL=openai/gpt-oss-120b
GROQ_BASE_URL=https://api.groq.com/openai/v1
```

Never commit a real API key. The `.env` file is intentionally excluded from Git.

## Testing

The test suite expects the separate MySQL database configured in `phpunit.xml`.

```bash
php artisan test
```

Run the frontend production build with:

```bash
npm run build
```

## Repository structure

This branch groups Laravel's application source beneath `src/` while keeping runtime, dependency, test, and documentation entry points at the repository root.

| Path | Purpose |
| --- | --- |
| `src/app/` | Application controllers, models, services, and domain logic |
| `src/bootstrap/` | Laravel application bootstrap |
| `src/config/` | Application and service configuration |
| `src/database/` | Migrations, factories, and seeders |
| `src/resources/` | Blade views, JavaScript, CSS, and source assets |
| `src/routes/` | Web, console, and broadcast routes |
| `assets/` | Design references and non-runtime media |
| `docs/` | Project documentation and reports |
| `public/` | Web entry point and public static files |
| `storage/` | Runtime files, logs, cache, and private uploads |
| `tests/` | Pest feature and unit tests |

See [Repository Structure](docs/REPOSITORY_STRUCTURE.md) for the path customizations and dependency-file equivalents.

## Documentation

- [Documentation index](docs/README.md)
- [Project document](docs/TEAM-ATHENA.pdf)

## Contributing

Create a focused branch for each change, verify the application locally, and open a pull request into `master`. Do not commit `.env`, API keys, uploaded research files, `vendor/`, or `node_modules/`.

## License

ATHENA is available under the [MIT License](LICENSE).

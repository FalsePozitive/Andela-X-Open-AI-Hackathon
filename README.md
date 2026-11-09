# Story Passport + Community Ledger

A PHP 8.3 / MySQL hackathon proof of concept. Every passport records a **claim and declared terms**, not proof of ownership. Every ledger payment is **MOCK**.

## Run locally

Requirements: PHP 8.3+ with PDO MySQL, fileinfo, GD, mbstring, session; MySQL 8 or MariaDB 10.11; Composer; Node 18+ for CSS builds only.

1. `composer install`
2. `npm ci && npm run build`
3. Create an empty MySQL database, preferably named `story_passport_demo`.
4. Copy `config/example.php` to ignored `config/local.php`. Set database access, base URL, and an absolute private storage path. Create that storage directory. Generate a password hash with `php bin/console.php password` and paste the hash into the config. Never commit credentials.
5. `php bin/console.php preflight`
6. `php bin/console.php migrate`
7. `php bin/console.php seed`
8. `php -S 127.0.0.1:8087 -t public public/router.php`

For loopback development set `environment` to `development` and `base_url` to `http://127.0.0.1:8087`. Production requires HTTPS and secure session cookies. Sign in at `/login` using the configured steward account.

The optional `bin/setup-local.php` is a developer convenience for an **already-running isolated MySQL on port 3317** with empty-password loopback root. It creates ignored local/test configurations; it is excluded from release packages. It never starts or reconfigures an existing MySQL installation.

## Checks

```text
php bin/console.php lint
php vendor/phpstan/phpstan/phpstan analyse --no-progress
php vendor/phpunit/phpunit/phpunit
npm run build
```

Database integration tests require ignored `config/test.php` pointing to a dedicated database named exactly `story_passport_test`. Copy local settings, change the database and private storage path, and apply migrations against it. Tests create fixtures and do not truncate a database. HTTP tests additionally require `PASSPORT_TEST_URL` pointing to the PHP server running with `PASSPORT_CONFIG` set to that test configuration. Never point these tests at a live site.

## Handover

- [Deployment](docs/DEPLOYMENT.md): Xneelo layout, configuration, upload, backup, rollback.
- [Demo](docs/DEMO.md): exact three-minute script and fallback.
- [Specification](docs/SPEC.md): current PHP interfaces and resolved design decisions.
- [Review](docs/REVIEW.md): results and checks that still require hosting access.

The initial implementation spans registry, rules, frontend, demo and QA ownership areas under the user-approved unified build plan. No external payment, identity, email-sending, analytics, or AI services are integrated.

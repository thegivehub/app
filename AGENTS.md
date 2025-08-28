# Repository Guidelines

## Project Structure & Module Organization
- `lib/`: Core PHP domain logic (e.g., `Campaign.php`, `TransactionProcessor.php`).
- `public/`: Public assets and demo pages (serve locally from here), e.g., `public/index.html`.
- `src/app/`: Frontend pages and UI experiments.
- `tests/`: PHPUnit tests and helpers (`tests/Unit` runs in CI).
- `cypress/`: End‑to‑end specs, videos, and screenshots.
- `assets/`, `css/`, `img/`: Static assets.
- `config/`, `tools/`, `docker-compose.yaml`, `Dockerfile`: Ops and local runtime.

## Build, Test, and Development Commands
- PHP deps: `composer install`
- Node deps (for e2e/load): `npm install`
- Run API locally (simple): `bash scripts/start-dev-server.sh` (creates compatibility symlinks and serves the project root)
- Alternative: `php -S 0.0.0.0:8080 -t .` (serve project root to expose admin/ impact/ portal/)
- Docker (preferred full stack): `docker compose up --build`
- Unit tests: `vendor/bin/phpunit --testsuite Unit`
- Coverage (quick text): `vendor/bin/phpunit --coverage-text`
- E2E tests (headless): `npm run cypress:run`
- E2E tests (GUI): `npm run cypress:open`

## Coding Style & Naming Conventions
- PHP: 4‑space indent, trailing commas where valid, strict comparisons when possible.
- Classes: PascalCase files in `lib/` (e.g., `UserManager.php`).
- Methods/vars: camelCase; constants UPPER_SNAKE_CASE.
- JS/HTML in `src/app/`: keep modules small; prefer descriptive IDs/classes.
- No secrets in code; use `.env` (see `.env.example`).

## Testing Guidelines
- Framework: PHPUnit 10; tests live under `tests/Unit` (name `*Test.php`).
- Bootstrapping: `tests/bootstrap.php` sets env and autoloading.
- Target: prioritize unit tests for `lib/` classes; mock external services.
- E2E: Cypress baseUrl configured in `cypress.config.js`; keep specs independent.

## Commit & Pull Request Guidelines
- Commits: follow Conventional Commits (e.g., `feat:`, `fix:`, `test:`). Example: `feat: add risk scoring to admin`.
- Branches: `feature/<short-desc>` or `fix/<short-desc>`.
- PRs must include: clear description, linked issues, test steps, and screenshots or logs for UI/E2E changes. Update `CHANGELOG.md` when user‑visible behavior changes.

## Security & Configuration Tips
- Configure via env vars (MongoDB, JWT, storage paths). Do not commit `.env`.
- For local dev, create writable `uploads/` subdirs as needed.
- Store CI secrets in the runner; never hardcode credentials in specs.


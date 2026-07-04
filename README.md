# Workflow Automate

A from-scratch WordPress workflow automation plugin. See `CURSOR_INSTRUCTIONS.md` for the governing process, `docs/internal/bitpi-analysis.md` for the competitive analysis that informed this design, and `docs/internal/architecture.md` for the architecture this codebase follows.

## Requirements

- PHP 7.4+
- WordPress 5.8+
- Optional: [Composer](https://getcomposer.org/) for dependency management and code-quality tooling.

## Local setup

1. Place (or symlink) this directory into `wp-content/plugins/`.
2. If you have Composer available:
   ```bash
   composer install
   ```
   This generates `vendor/autoload.php`, which the plugin will use automatically in preference to its built-in fallback autoloader.
3. If Composer is **not** available in your environment, no action is needed — the plugin ships a small dependency-free PSR-4 autoloader (`src/autoload.php`) that is used automatically when `vendor/autoload.php` is missing. This keeps the plugin fully functional in environments without a Composer/PHP CLI on `PATH` (e.g. some local Windows setups), while still preferring the standard Composer-generated autoloader when one exists.
4. Activate **Workflow Automate** from the WordPress admin Plugins screen.

## Coding standards & linting

- **PHP:** [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards) via PHPCS, configured in `phpcs.xml.dist`.
  ```bash
  composer install
  composer phpcs   # report violations
  composer phpcbf  # auto-fix what can be fixed
  ```
- **JavaScript:** `@wordpress/eslint-plugin`, configured in `.eslintrc`.
  ```bash
  npm install
  npm run lint:js
  npm run lint:js:fix
  ```

## Project structure

```
workflow-automate.php   Plugin bootstrap (header, version gate, autoload, activation hooks)
uninstall.php           WordPress-invoked cleanup entry point
src/
  autoload.php           Fallback PSR-4 autoloader (see "Local setup" above)
  Core/                  Bootstrap: Plugin, Container, Requirements, Activator, Deactivator, Uninstaller, Options
  Database/              Migration base class/runner, table-name helper, migrations (dbDelta-based schema)
  Domain/                Plain, persistence-agnostic entities (Workflow, WorkflowNode)
  Persistence/            Repositories wrapping $wpdb access (one per aggregate root)
  Service/               Application services orchestrating domain + persistence (WorkflowService)
docs/
  hooks-reference.md     Public actions/filters reference
  internal/              Development-only docs (analysis, architecture, roadmap) — excluded from release builds
```

## Development process

This project follows a strict, incremental, feature-by-feature workflow with a mandatory self/security/performance/standards review after every increment. See `CURSOR_INSTRUCTIONS.md` Sections 9–11 and `docs/internal/roadmap.md` for the current status of each increment.

## Release packaging

`/docs/internal/` must be excluded from any distributable ZIP built for release; it is development-only documentation.

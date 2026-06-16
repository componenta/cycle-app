# Componenta Cycle App

Application integration for `componenta/cycle`. This package connects Cycle runtime services to framework discovery, compiled configuration, and console commands.

Use it in a Componenta application that wants framework-managed Cycle discovery. Libraries should depend on `componenta/cycle` only.

## Installation

```bash
composer require componenta/cycle-app
```

Register its provider after the Cycle runtime provider:

```php
return [
    new Componenta\Cycle\ConfigProvider(),
    new Componenta\Cycle\App\ConfigProvider(),
];
```

## Related Packages

| Package | Why it matters here |
|---|---|
| `componenta/cycle` | Owns repositories, data fetchers, filters, typecasts, and runtime factories. |
| `componenta/class-finder` | Finds entities and embeddables in configured directories. |
| `componenta/app` | Runs cache compilation and chooses development or production loading. |
| `cycle/orm` | Consumes the final ORM configuration. |

## What It Adds

The package provides app-level integration for:

- entity and embeddable discovery
- locator services backed by the configured class iterator
- `ClassFinderConfigKey::LISTENERS` entries for `EntityLocator` and `EmbeddingLocator`
- Cycle-related console commands registered through `Componenta\App\Console\ConfigKey::COMMANDS`
- cache/compiler integration used by the application build process

## Console Commands

When `componenta/app-console` is installed, this package contributes the database commands below to the shared console command graph. They are registered through configuration, so they are available in production builds without relying on attribute scanning.

| Command | Purpose |
|---|---|
| `db:create` | Create the configured database when the driver supports it. |
| `db:generate` | Generate migrations from the current ORM schema diff. |
| `db:schema` | Generate and cache the Cycle ORM schema. |
| `db:migrate` | Execute pending migrations. |
| `db:rollback` | Roll back migrations. |
| `db:status` | Show migration status. |
| `db:sync` | Generate and apply migrations, then regenerate ORM schema. |

```bash
php bin/console.php db:status
php bin/console.php db:migrate
php bin/console.php db:sync
```

## Development Mode

In development, the application may scan configured source directories and derive Cycle mappings from discovered classes. This keeps module configuration focused on the classes it owns.

## Production Mode

In production, the application should use compiled config and generated cache artifacts. It should not scan source directories or rebuild ORM metadata during each request.

## Boundaries

This package should not contain persistence behavior that a runtime consumer needs directly. Repositories, data fetchers, filters, typecasts, and runtime factories belong to `componenta/cycle`.

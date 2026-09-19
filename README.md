# Componenta Cycle App

Application integration for `componenta/cycle`. This package connects Cycle runtime services to framework discovery and console commands.

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
| `componenta/app` | Prepares shared discovery and runs the application bootloaders. |
| `cycle/orm` | Consumes the final ORM configuration. |

## What It Adds

The package provides app-level integration for:

- entity and embeddable discovery
- locator services backed by the configured class iterator
- `ClassFinderConfigKey::LISTENERS` entries for `EntityLocator` and `EmbeddingLocator`
- Cycle-related console commands registered through `Componenta\App\Console\ConfigKey::COMMANDS`

## Console Commands

When `componenta/app-console` is installed, this package contributes the database commands below to the shared console command graph. They are registered through configuration in both development and production.

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

## Discovery and ORM Schema

Entity and embeddable locators use the application's shared class iterator in both development and production. `ConfigFactory` prepares this iterator, and the class discovery bootloader notifies the registered listeners.

Generate the ORM schema with `db:schema`, or as part of `db:sync` or `db:migrate --schema`. The Cycle runtime creates its schema from the resulting application configuration.

## Boundaries

This package should not contain persistence behavior that a runtime consumer needs directly. Repositories, data fetchers, filters, typecasts, and runtime factories belong to `componenta/cycle`.

# Componenta Cycle App

Application integration for `componenta/cycle`. This package connects Cycle runtime services to framework discovery and console commands.

Use it in a Componenta application that wants framework-managed Cycle discovery. Libraries should depend on `componenta/cycle` only.

## Installation

```bash
composer require componenta/cycle-app
```

Version 3 requires PHP 8.5.13+, App 6, Console 6, Config 4 and Cycle 2. It adopts
the shared application build contract. Version 2 applications using Config 3 and
Console 5 must upgrade those dependencies together.

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
| `componenta/app` | Prepares shared discovery, publishes build generations and runs application bootloaders. |
| `cycle/orm` | Consumes the final ORM configuration. |

## What It Adds

The package provides app-level integration for:

- entity and embeddable discovery
- locator services backed by the configured class iterator
- `ClassFinderConfigKey::LISTENERS` entries for `EntityLocator` and `EmbeddingLocator`
- Cycle-related console commands registered through `Componenta\App\Console\ConfigKey::COMMANDS`
- an optional `cycle.schema` participant in the shared application build

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

## Optional Application Build

Schema preparation is disabled by default. Enable it explicitly in application
configuration if the build environment can read the configured database metadata:

```php
use Componenta\Cycle\App\ConfigKey;

return [
    ConfigKey::PREPARE_SCHEMA => true,
];
```

The package registers `Build\SchemaBuilder` through `Componenta\App\ConfigKey::BUILDERS`.
It uses current source discovery and fresh entity/embedding locators, without
running application bootloaders. `SchemaCompiler` is shared with `db:schema`.
The participant writes the schema payload through `BuildContext`; App owns
generation paths, atomic publication and cleaning. It does not write a separate
`config/autoload/cycle.local.php` file. The existing `db:schema --output=...`
command still writes that standalone configuration when requested.

Compilation can read table metadata through Cycle's database provider and run
trusted entity attributes and schema modifiers. It is not an offline build
guarantee. The compiler does not run schema synchronization, execute migrations,
or copy database records into an artifact. Database deployment commands remain
separate. When preparation is disabled, the participant records a skip without
resolving discovery or database services.

The runtime adapter selects schema in this order:

1. Explicit `Componenta\Cycle\ConfigKey::ROOT` / `SCHEMA` configuration, including
   an intentionally empty schema. The builder skips compilation when this value
   is present.
2. A compatible `cycle.schema` payload from the bootstrap's selected
   `PreparedBuild`, when `PREPARE_SCHEMA` is enabled.
3. Cycle's existing absent-schema behavior: an empty schema. A missing artifact
   never starts a database-backed compilation during a request.

Nonportable values, including runtime objects and closures, cause this optional
artifact to be marked skipped. Use explicit runtime schema configuration for
such values. Discovery and compiler failures propagate and prevent publication
of the new generation. A deployment that requires a prepared schema can include
`ConfigKey::SCHEMA_ARTIFACT` in App's required-artifact policy; a skipped or missing
schema then prevents activation. Do not require that artifact when intentionally
using an explicit schema configuration instead.

Prepared maps are tied to a deployment generation. Rebuild when entity mappings,
schema modifiers, or relevant database configuration change; long-running workers
keep their selected generation until restarted. This package does not watch
database metadata or regenerate artifacts in serving processes.

## Boundaries

This package should not contain persistence behavior that a runtime consumer needs directly. Repositories, data fetchers, filters, typecasts, and runtime factories belong to `componenta/cycle`.

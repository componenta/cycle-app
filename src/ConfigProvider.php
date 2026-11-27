<?php

declare(strict_types=1);

namespace Componenta\Cycle\App;

use Componenta\App\Console\ConfigKey as ConsoleConfigKey;
use Componenta\App\ConfigKey as AppConfigKey;
use Componenta\ClassFinder\ConfigKey as ClassFinderConfigKey;
use Componenta\Config\ConfigProvider as BaseConfigProvider;
use Componenta\Cycle\App\Console\CreateCommand;
use Componenta\Cycle\App\Console\GenerateCommand;
use Componenta\Cycle\App\Console\GenerateSchemaCommand;
use Componenta\Cycle\App\Console\MigrateCommand;
use Componenta\Cycle\App\Console\RollbackCommand;
use Componenta\Cycle\App\Console\StatusCommand;
use Componenta\Cycle\App\Console\SyncCommand;
use Componenta\Cycle\App\Factory\EmbeddingLocatorFactory;
use Componenta\Cycle\App\Factory\EntityLocatorFactory;
use Componenta\Cycle\App\Build\SchemaBuilder;
use Componenta\Cycle\App\Factory\SchemaBuilderFactory;
use Componenta\Cycle\App\Factory\SchemaFactory;
use Componenta\Cycle\App\Locator\EmbeddingLocator;
use Componenta\Cycle\App\Locator\EntityLocator;
use Cycle\Annotated\Locator\EmbeddingLocatorInterface;
use Cycle\Annotated\Locator\EntityLocatorInterface;
use Cycle\ORM\SchemaInterface;

final class ConfigProvider extends BaseConfigProvider
{
    protected function getFactories(): array
    {
        return [
            EntityLocator::class => EntityLocatorFactory::class,
            EmbeddingLocator::class => EmbeddingLocatorFactory::class,
            SchemaBuilder::class => SchemaBuilderFactory::class,
            SchemaInterface::class => SchemaFactory::class,
        ];
    }

    protected function getAliases(): array
    {
        return [
            EntityLocatorInterface::class => EntityLocator::class,
            EmbeddingLocatorInterface::class => EmbeddingLocator::class,
        ];
    }

    protected function getConfig(): array
    {
        return [
            AppConfigKey::BUILDERS => [SchemaBuilder::class],
            ClassFinderConfigKey::LISTENERS => [
                EntityLocator::class,
                EmbeddingLocator::class,
            ],
            ConsoleConfigKey::COMMANDS => [
                CreateCommand::class,
                GenerateCommand::class,
                GenerateSchemaCommand::class,
                MigrateCommand::class,
                RollbackCommand::class,
                StatusCommand::class,
                SyncCommand::class,
            ],
        ];
    }
}

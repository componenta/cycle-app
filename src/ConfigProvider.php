<?php

declare(strict_types=1);

namespace Componenta\Cycle\App;

use Componenta\App\Console\ConfigKey as ConsoleConfigKey;
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
use Componenta\Cycle\App\Locator\EmbeddingLocator;
use Componenta\Cycle\App\Locator\EntityLocator;
use Cycle\Annotated\Locator\EmbeddingLocatorInterface;
use Cycle\Annotated\Locator\EntityLocatorInterface;

final class ConfigProvider extends BaseConfigProvider
{
    protected function getFactories(): array
    {
        return [
            EntityLocator::class => EntityLocatorFactory::class,
            EmbeddingLocator::class => EmbeddingLocatorFactory::class,
        ];
    }

    protected function getAutowires(): array
    {
        return [
            CreateCommand::class,
            GenerateCommand::class,
            GenerateSchemaCommand::class,
            MigrateCommand::class,
            RollbackCommand::class,
            StatusCommand::class,
            SyncCommand::class,
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

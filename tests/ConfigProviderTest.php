<?php

declare(strict_types=1);

use Componenta\Cycle\App\ConfigProvider;
use Componenta\Cycle\App\Console\CreateCommand;
use Componenta\Cycle\App\Console\GenerateCommand;
use Componenta\Cycle\App\Console\GenerateSchemaCommand;
use Componenta\Cycle\App\Console\MigrateCommand;
use Componenta\Cycle\App\Console\RollbackCommand;
use Componenta\Cycle\App\Console\StatusCommand;
use Componenta\Cycle\App\Console\SyncCommand;
use Componenta\Cycle\App\Locator\EmbeddingLocator;
use Componenta\Cycle\App\Locator\EntityLocator;
use Componenta\App\Console\ConfigKey as ConsoleConfigKey;
use Componenta\App\ConfigKey as AppConfigKey;
use Componenta\Cycle\App\Build\SchemaBuilder;
use Componenta\Cycle\App\Factory\SchemaFactory;
use Componenta\ClassFinder\ConfigKey as ClassFinderConfigKey;
use Componenta\Config\ConfigKey as DependencyConfigKey;
use Cycle\Annotated\Locator\EmbeddingLocatorInterface;
use Cycle\Annotated\Locator\EntityLocatorInterface;
use Cycle\ORM\SchemaInterface;

it('registers Cycle discovery locators through app integration', function (): void {
    $config = (new ConfigProvider())();

    expect($config['dependencies']['aliases'][EntityLocatorInterface::class])->toBe(EntityLocator::class)
        ->and($config['dependencies']['aliases'][EmbeddingLocatorInterface::class])->toBe(EmbeddingLocator::class)
        ->and($config[ClassFinderConfigKey::LISTENERS])->toBe([
            EntityLocator::class,
            EmbeddingLocator::class,
        ]);
});

it('registers the optional schema participant and prepared runtime adapter', function (): void {
    $config = (new ConfigProvider())();

    expect($config[AppConfigKey::BUILDERS])->toBe([SchemaBuilder::class])
        ->and($config['dependencies']['factories'][SchemaInterface::class])->toBe(SchemaFactory::class);
});

it('registers Cycle database console commands', function (): void {
    $config = (new ConfigProvider())();
    $commands = [
        CreateCommand::class,
        GenerateCommand::class,
        GenerateSchemaCommand::class,
        MigrateCommand::class,
        RollbackCommand::class,
        StatusCommand::class,
        SyncCommand::class,
    ];

    expect($config[ConsoleConfigKey::COMMANDS])->toBe($commands)
        ->and($config[DependencyConfigKey::DEPENDENCIES])->not->toHaveKey('autowires');
});

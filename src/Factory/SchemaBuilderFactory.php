<?php

declare(strict_types=1);

namespace Componenta\Cycle\App\Factory;

use Componenta\App\ConfigKey as AppConfigKey;
use Componenta\ClassFinder\ClassIteratorInterface;
use Componenta\Config\ContainerValue;
use Componenta\Cycle\App\Build\SchemaBuilder;
use Componenta\Cycle\App\ConfigKey;
use Componenta\Cycle\App\SchemaCompiler;
use Componenta\Cycle\ConfigKey as CycleConfigKey;
use Cycle\Database\DatabaseProviderInterface;

final class SchemaBuilderFactory
{
    public function __invoke(ContainerValue $container): SchemaBuilder
    {
        if (!$container->config->bool(ConfigKey::PREPARE_SCHEMA, false)) {
            return new SchemaBuilder();
        }

        $cycle = $container->config->array(CycleConfigKey::ROOT, []);
        if (array_key_exists(CycleConfigKey::SCHEMA, $cycle)) {
            return new SchemaBuilder(null, 'Explicit Cycle schema configuration takes precedence.');
        }

        return new SchemaBuilder(static function () use ($container): array {
            $classes = $container->get(AppConfigKey::DISCOVERY_SOURCE, ClassIteratorInterface::class);
            $compiler = new SchemaCompiler(
                $container->get(DatabaseProviderInterface::class, DatabaseProviderInterface::class),
            );

            return $compiler->fromClasses($classes);
        });
    }
}

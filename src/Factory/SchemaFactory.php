<?php

declare(strict_types=1);

namespace Componenta\Cycle\App\Factory;

use Componenta\App\Build\PreparedBuild;
use Componenta\App\ConfigKey as AppConfigKey;
use Componenta\Config\ContainerValue;
use Componenta\Cycle\App\ConfigKey;
use Componenta\Cycle\ConfigKey as CycleConfigKey;
use Cycle\ORM\Schema;

/** Runtime adapter; explicit configuration always wins and a miss never compiles. */
final class SchemaFactory
{
    public function __invoke(ContainerValue $container): Schema
    {
        $cycle = $container->config->array(CycleConfigKey::ROOT, []);
        if (array_key_exists(CycleConfigKey::SCHEMA, $cycle)) {
            return new Schema($cycle[CycleConfigKey::SCHEMA] ?? []);
        }

        if ($container->config->bool(ConfigKey::PREPARE_SCHEMA, false)
            && $container->has(AppConfigKey::PREPARED_BUILD)
        ) {
            $payload = $container->get(AppConfigKey::PREPARED_BUILD, PreparedBuild::class)
                ->read(ConfigKey::SCHEMA_ARTIFACT);
            if ($payload !== null && self::validPayload($payload)) {
                return new Schema($payload['schema']);
            }
        }

        // Preserve Cycle's native absent-schema behavior. db:schema remains available.
        return new Schema([]);
    }

    private static function validPayload(array $payload): bool
    {
        if (array_keys($payload) !== ['schema'] || !is_array($payload['schema'])) {
            return false;
        }

        foreach ($payload['schema'] as $role => $definition) {
            if (!is_string($role) || $role === '' || !is_array($definition)) {
                return false;
            }
        }

        return true;
    }
}

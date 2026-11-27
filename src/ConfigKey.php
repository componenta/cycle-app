<?php

declare(strict_types=1);

namespace Componenta\Cycle\App;

final class ConfigKey
{
    /** Opt in to schema preparation and loading; compilation can read database metadata. */
    public const string PREPARE_SCHEMA = 'cycle.prepare_schema';

    public const string SCHEMA_ARTIFACT = 'cycle.schema';
}

<?php

declare(strict_types=1);

namespace Componenta\Cycle\App\Build;

use Closure;
use Componenta\App\Build\ApplicationBuilderInterface;
use Componenta\App\Build\BuildContext;
use Componenta\Cycle\App\ConfigKey;
use Componenta\VarExport\Exception\ExportException;
use Componenta\VarExport\Exception\UnsupportedValueException;

final readonly class SchemaBuilder implements ApplicationBuilderInterface
{
    /** @param (Closure(): array)|null $prepare Lazy operation; construction never resolves database services. */
    public function __construct(
        private ?Closure $prepare = null,
        private string $skipReason = 'Cycle schema preparation is disabled.',
    ) {}

    public function build(BuildContext $build): void
    {
        if ($this->prepare === null) {
            $build->skip(ConfigKey::SCHEMA_ARTIFACT, $this->skipReason);
            return;
        }

        // Discovery, database and compiler errors are real build failures.
        $schema = ($this->prepare)();

        try {
            $build->write(ConfigKey::SCHEMA_ARTIFACT, ['schema' => $schema]);
        } catch (ExportException|UnsupportedValueException) {
            $build->skip(
                ConfigKey::SCHEMA_ARTIFACT,
                'Cycle schema contains values that cannot be prepared; use explicit runtime schema configuration.',
            );
        }
    }
}

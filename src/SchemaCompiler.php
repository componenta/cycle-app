<?php

declare(strict_types=1);

namespace Componenta\Cycle\App;

use Componenta\ClassFinder\ClassIteratorInterface;
use Componenta\Cycle\App\Locator\EmbeddingLocator;
use Componenta\Cycle\App\Locator\EntityLocator;
use Componenta\Cycle\Mapper\LazyGhostMapper;
use Cycle\Annotated;
use Cycle\Annotated\Locator\EmbeddingLocatorInterface;
use Cycle\Annotated\Locator\EntityLocatorInterface;
use Cycle\Database\DatabaseProviderInterface;
use Cycle\ORM\SchemaInterface;
use Cycle\Schema;

/** Compiles ORM metadata; never runs database synchronization or migrations. */
final readonly class SchemaCompiler
{
    public function __construct(private DatabaseProviderInterface $databases) {}

    public function compile(
        EntityLocatorInterface $entities,
        EmbeddingLocatorInterface $embeddings,
    ): array {
        $defaults = (new Schema\Defaults())->merge([
            SchemaInterface::MAPPER => LazyGhostMapper::class,
        ]);

        return (new Schema\Compiler())->compile(
            new Schema\Registry($this->databases, $defaults),
            [
                new Annotated\Embeddings($embeddings),
                new Annotated\Entities($entities),
                new Annotated\TableInheritance(),
                new Annotated\MergeColumns(),
                new Schema\Generator\ResetTables(),
                new Schema\Generator\GenerateRelations(),
                new Schema\Generator\GenerateModifiers(),
                new Schema\Generator\ValidateEntities(),
                new Schema\Generator\RenderTables(),
                new Schema\Generator\RenderRelations(),
                new Schema\Generator\RenderModifiers(),
                new Schema\Generator\ForeignKeys(),
                new Annotated\MergeIndexes(),
                new Schema\Generator\GenerateTypecast(),
            ],
        );
    }

    /** Build from current source without running application discovery bootloaders. */
    public function fromClasses(ClassIteratorInterface $classes): array
    {
        $entities = new EntityLocator();
        $embeddings = new EmbeddingLocator();

        foreach ($classes as $class) {
            $entities->handle($class);
            $embeddings->handle($class);
        }

        $entities->finalize();
        $embeddings->finalize();

        return $this->compile($entities, $embeddings);
    }
}

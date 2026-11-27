<?php

declare(strict_types=1);

namespace Componenta\Cycle\App\Tests;

use Attribute;
use Closure;
use Componenta\Cycle\App\Console\GenerateSchemaCommand;
use Componenta\Cycle\App\Locator\EmbeddingLocator;
use Componenta\Cycle\App\Locator\EntityLocator;
use Componenta\Cycle\App\SchemaCompiler;
use Componenta\Cycle\ConfigKey;
use Componenta\Tokenizer\ClassInfo;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Database\Config\SQLiteDriverConfig;
use Cycle\Database\Database;
use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseProviderInterface;
use Cycle\Database\Driver\SQLite\SQLiteDriver;
use Cycle\ORM\Schema;
use Cycle\ORM\SchemaInterface;
use Cycle\Schema\Registry;
use Cycle\Schema\SchemaModifierInterface;
use ReflectionFunction;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

it('preserves unscoped typecast visibility when schema configuration is loaded inside a class', function (): void {
    $entities = new EntityLocator();
    $entities->handle(new ClassInfo(UnscopedTypecastEntity::class));
    $entities->finalize();
    $embeddings = new EmbeddingLocator();
    $embeddings->finalize();
    $database = new Database('typecast-scope', '', SQLiteDriver::create(new SQLiteDriverConfig()));
    $databases = new class ($database) implements DatabaseProviderInterface {
        public function __construct(private DatabaseInterface $connection) {}
        public function database(?string $database = null): DatabaseInterface { return $this->connection; }
    };
    $source = new Schema((new SchemaCompiler($databases))->compile($entities, $embeddings));
    $original = $source->define('unscoped-typecast', SchemaInterface::TYPECAST)['value'];
    $directory = sys_get_temp_dir() . '/componenta-schema-typecast-scope-' . bin2hex(random_bytes(10));
    $file = $directory . '/schema.php';

    try {
        $command = new CommandTester(new GenerateSchemaCommand($databases, $entities, $embeddings));
        expect($command->execute(['--output' => $file]))->toBe(Command::SUCCESS);
        $config = (new SchemaTypecastLoadScope())->load($file);
        $restored = (new Schema($config[ConfigKey::ROOT][ConfigKey::SCHEMA]))
            ->define('unscoped-typecast', SchemaInterface::TYPECAST)['value'];

        expect($original(PublicSchemaTypecastValue::class))->toBe(7)
            ->and($restored(PublicSchemaTypecastValue::class))->toBe(7)
            ->and(fn () => $original(SchemaTypecastLoadScope::class))->toThrow(\Error::class, 'Cannot access private constant')
            ->and(fn () => $restored(SchemaTypecastLoadScope::class))->toThrow(\Error::class, 'Cannot access private constant')
            ->and((new ReflectionFunction($original))->getClosureScopeClass())->toBeNull()
            ->and((new ReflectionFunction($restored))->getClosureScopeClass())->toBeNull();
    } finally {
        if (is_file($file)) {
            unlink($file);
        }
        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
});

// A free function creates a genuinely unscoped closure, unlike Pest's bound test callback.
function unscopedSchemaTypecast(): Closure
{
    return static fn (string $class): int => $class::VALUE;
}

#[Entity(role: 'unscoped-typecast', table: 'unscoped_typecast')]
#[UnscopedTypecastModifier]
final class UnscopedTypecastEntity
{
    #[Column(type: 'primary')]
    public int $id;

    #[Column(type: 'integer')]
    public int $value;
}

#[Attribute(Attribute::TARGET_CLASS)]
final class UnscopedTypecastModifier implements SchemaModifierInterface
{
    public function withRole(string $role): static { return clone $this; }
    public function compute(Registry $registry): void {}
    public function render(Registry $registry): void {}
    public function modifySchema(array &$schema): void
    {
        $schema[SchemaInterface::TYPECAST]['value'] = unscopedSchemaTypecast();
    }
}

final class PublicSchemaTypecastValue
{
    public const int VALUE = 7;
}

final class SchemaTypecastLoadScope
{
    private const int VALUE = 42;

    public function load(string $file): array { return require $file; }
}

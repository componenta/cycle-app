<?php

declare(strict_types=1);

namespace Componenta\Cycle\App\Tests;

use Attribute;
use Componenta\Cycle\App\Console\GenerateSchemaCommand;
use Componenta\Cycle\App\Locator\EmbeddingLocator;
use Componenta\Cycle\App\Locator\EntityLocator;
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
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

it('preserves schema values when the generated config is loaded by the ORM', function (): void {
    $entities = new EntityLocator();
    $entities->handle(new ClassInfo(SchemaExportEntity::class));
    $entities->finalize();
    $embeddings = new EmbeddingLocator();
    $embeddings->finalize();

    $database = new Database('schema-export-test', '', SQLiteDriver::create(new SQLiteDriverConfig()));
    $databases = new class ($database) implements DatabaseProviderInterface {
        public function __construct(private DatabaseInterface $connection) {}
        public function database(?string $database = null): DatabaseInterface { return $this->connection; }
    };
    $directory = sys_get_temp_dir() . '/componenta-schema-export-' . bin2hex(random_bytes(10));
    $file = $directory . '/schema.php';

    try {
        $command = new CommandTester(new GenerateSchemaCommand($databases, $entities, $embeddings));
        expect($command->execute(['--output' => $file]))->toBe(Command::SUCCESS);
        $config = require $file;
        $schema = new Schema($config[ConfigKey::ROOT][ConfigKey::SCHEMA]);
        $metadata = $schema->define('schema-export', SchemaInterface::SCHEMA);

        expect($metadata)->toBe(SchemaExportMetadata::VALUES)
            ->and(pack('d', $metadata['negative-zero']))->toBe(pack('d', -0.0))
            ->and($schema->define('schema-export', SchemaInterface::ENTITY))->toBe(SchemaExportEntity::class);

        file_put_contents($file, '<?php return ["stale" => true];');
        expect($command->execute(['--output' => $file]))->toBe(Command::SUCCESS);
        expect(require $file)->toBe($config);
    } finally {
        if (is_file($file)) {
            unlink($file);
        }
        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
});

it('reports a failed schema write and cleans up unpublished files', function (): void {
    $entities = new EntityLocator();
    $entities->finalize();
    $embeddings = new EmbeddingLocator();
    $embeddings->finalize();
    $databases = new class implements DatabaseProviderInterface {
        public function database(?string $database = null): DatabaseInterface
        {
            throw new \LogicException('Empty discovery must not access a database.');
        }
    };
    $directory = sys_get_temp_dir() . '/componenta-schema-failure-' . bin2hex(random_bytes(10));
    mkdir($directory);
    $target = $directory . '/schema.php';
    mkdir($target);

    $application = new Application();
    $application->setAutoExit(false);
    $application->addCommand(new GenerateSchemaCommand($databases, $entities, $embeddings));
    $output = new BufferedOutput();
    $previous = static fn (int $severity): bool => $severity === E_WARNING;
    set_error_handler($previous);
    try {
        $status = $application->run(new ArrayInput(['command' => 'db:schema', '--output' => $target]), $output);
        $restored = set_error_handler($previous);
        restore_error_handler();
        $display = $output->fetch();

        expect($status)->not->toBe(Command::SUCCESS);
        expect($display)->toContain('schema.php')->not->toContain('Schema written');
        expect($restored)->toBe($previous);
        expect(scandir($directory))->toBe(['.', '..', 'schema.php']);
    } finally {
        restore_error_handler();
        foreach (scandir($directory) as $name) {
            if ($name !== '.' && $name !== '..' && is_file($directory . '/' . $name)) {
                unlink($directory . '/' . $name);
            }
        }
        rmdir($target);
        rmdir($directory);
    }
});

it('retains the previous schema when writing the replacement stops partway through', function (): void {
    $directory = sys_get_temp_dir() . '/componenta-schema-partial-' . bin2hex(random_bytes(10));
    mkdir($directory);
    $target = $directory . '/schema.php';
    $previous = '<?php return ["previous" => true];';
    file_put_contents($target, $previous);
    SchemaPartialWriteStream::$directory = $directory;
    stream_wrapper_register('schemapartial', SchemaPartialWriteStream::class);
    $entities = new EntityLocator();
    $entities->finalize();
    $embeddings = new EmbeddingLocator();
    $embeddings->finalize();
    $databases = new class implements DatabaseProviderInterface {
        public function database(?string $database = null): DatabaseInterface
        {
            throw new \LogicException('Empty discovery must not access a database.');
        }
    };
    $application = new Application();
    $application->setAutoExit(false);
    $application->addCommand(new GenerateSchemaCommand($databases, $entities, $embeddings));

    $output = new BufferedOutput();
    try {
        $status = $application->run(
            new ArrayInput(['command' => 'db:schema', '--output' => 'schemapartial://cache/schema.php']),
            $output,
        );
        expect($status)->not->toBe(Command::SUCCESS);
        expect($output->fetch())->toContain('schema.php')->not->toContain('Schema written');
        expect(file_get_contents($target))->toBe($previous);
        expect(scandir($directory))->toBe(['.', '..', 'schema.php']);
    } finally {
        stream_wrapper_unregister('schemapartial');
        foreach (scandir($directory) as $name) {
            if ($name !== '.' && $name !== '..') {
                unlink($directory . '/' . $name);
            }
        }
        rmdir($directory);
    }
});

#[Entity(role: 'schema-export', table: 'schema_export')]
#[SchemaExportMetadata]
final class SchemaExportEntity
{
    #[Column(type: 'primary')]
    public int $id;
}

#[Attribute(Attribute::TARGET_CLASS)]
final class SchemaExportMetadata implements SchemaModifierInterface
{
    public const array VALUES = [
        'label' => "A \"quote\", a 'quote', a slash \\, and a NUL \0",
        'float' => 1.0,
        'negative-zero' => -0.0,
        'list' => [false, null, 0, '0'],
    ];

    public function withRole(string $role): static { return clone $this; }
    public function compute(Registry $registry): void {}
    public function render(Registry $registry): void {}
    public function modifySchema(array &$schema): void
    {
        $schema[SchemaInterface::SCHEMA] = self::VALUES;
    }
}

/** Filesystem boundary that accepts only the first eight bytes of each write. */
final class SchemaPartialWriteStream
{
    public mixed $context;
    public static string $directory;
    private mixed $stream;
    private bool $started = false;

    private static function path(string $path): string
    {
        return self::$directory . substr($path, strlen('schemapartial://cache'));
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        $this->stream = fopen(self::path($path), $mode);
        return is_resource($this->stream);
    }

    public function stream_write(string $data): int
    {
        if ($this->started) {
            return 0;
        }
        $this->started = true;
        return fwrite($this->stream, substr($data, 0, 8));
    }

    public function stream_flush(): bool { return fflush($this->stream); }
    public function stream_close(): void { fclose($this->stream); }
    public function stream_stat(): array|false { return fstat($this->stream); }
    public function url_stat(string $path, int $flags): array|false
    {
        $file = self::path($path);
        return file_exists($file) ? stat($file) : false;
    }
    public function unlink(string $path): bool { return unlink(self::path($path)); }
    public function rename(string $from, string $to): bool { return rename(self::path($from), self::path($to)); }
}

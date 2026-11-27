<?php

declare(strict_types=1);

namespace Componenta\Cycle\App\Tests;

use Componenta\App\Build\ApplicationBuildOrchestrator;
use Componenta\App\Build\BuildStore;
use Componenta\App\Build\PreparedBuild;
use Componenta\App\ConfigKey as AppConfigKey;
use Componenta\ClassFinder\ClassIterator;
use Componenta\Config\Config;
use Componenta\Config\ConfigKey as DependencyConfigKey;
use Componenta\Config\ConfigFactory;
use Componenta\Config\ContainerValue;
use Componenta\Config\Environment;
use Componenta\Cycle\App\Build\SchemaBuilder;
use Componenta\Cycle\App\ConfigKey;
use Componenta\Cycle\App\ConfigProvider;
use Componenta\Cycle\App\Console\GenerateSchemaCommand;
use Componenta\Cycle\App\Factory\SchemaBuilderFactory;
use Componenta\Cycle\App\Factory\SchemaFactory;
use Componenta\Cycle\App\Locator\EmbeddingLocator;
use Componenta\Cycle\App\Locator\EntityLocator;
use Componenta\Cycle\ConfigKey as CycleConfigKey;
use Componenta\DI\ContainerFactory;
use Componenta\Tokenizer\ClassInfo;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\Database\Config\SQLiteDriverConfig;
use Cycle\Database\Database;
use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseProviderInterface;
use Cycle\Database\Driver\SQLite\SQLiteDriver;
use Cycle\ORM\SchemaInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function (): void {
    $this->buildDirectory = sys_get_temp_dir() . '/componenta-cycle-build-' . bin2hex(random_bytes(8));
});

afterEach(function (): void {
    if (!is_dir($this->buildDirectory)) {
        return;
    }
    $files = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($this->buildDirectory, \FilesystemIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($this->buildDirectory);
});

it('skips preparation by default without resolving discovery or database services', function (): void {
    $services = new CycleBuildContainer();
    $builder = (new SchemaBuilderFactory())(cycleBuildContainerValue($services));
    $store = new BuildStore($this->buildDirectory);
    $prepared = (new ApplicationBuildOrchestrator([$builder], $store))->build();

    expect($services->requested)->toBe([])
        ->and($prepared->read(ConfigKey::SCHEMA_ARTIFACT))->toBeNull()
        ->and($prepared->skipped())->toBe([
            ConfigKey::SCHEMA_ARTIFACT => 'Cycle schema preparation is disabled.',
        ]);
});

it('does not prepare a replacement for explicitly configured schema', function (): void {
    $services = new CycleBuildContainer();
    $builder = (new SchemaBuilderFactory())(cycleBuildContainerValue($services, [
        ConfigKey::PREPARE_SCHEMA => true,
        CycleConfigKey::ROOT => [CycleConfigKey::SCHEMA => []],
    ]));
    $prepared = (new ApplicationBuildOrchestrator([$builder], new BuildStore($this->buildDirectory)))->build();

    expect($services->requested)->toBe([])
        ->and($prepared->skipped())->toBe([
            ConfigKey::SCHEMA_ARTIFACT => 'Explicit Cycle schema configuration takes precedence.',
        ]);
});

it('prepares the command schema without synchronizing tables or changing database data', function (): void {
    $database = new Database('prepared-schema', '', SQLiteDriver::create(new SQLiteDriverConfig()));
    $database->execute('CREATE TABLE prepared_schema_records (id INTEGER PRIMARY KEY, label TEXT NOT NULL, sentinel TEXT)');
    $database->execute("INSERT INTO prepared_schema_records (id, label, sentinel) VALUES (1, 'keep', 'untouched')");
    $databases = new class ($database) implements DatabaseProviderInterface {
        public function __construct(private DatabaseInterface $database) {}
        public function database(?string $database = null): DatabaseInterface { return $this->database; }
    };
    $services = new CycleBuildContainer([
        AppConfigKey::DISCOVERY_SOURCE => new ClassIterator([new ClassInfo(PreparedSchemaEntity::class)]),
        DatabaseProviderInterface::class => $databases,
    ]);
    $builder = (new SchemaBuilderFactory())(cycleBuildContainerValue($services, [ConfigKey::PREPARE_SCHEMA => true]));
    expect($services->requested)->toBe([]);

    $prepared = (new ApplicationBuildOrchestrator([$builder], new BuildStore($this->buildDirectory)))->build();
    $payload = $prepared->read(ConfigKey::SCHEMA_ARTIFACT);
    $runtime = cycleBuildContainerValue(new CycleBuildContainer([
        AppConfigKey::PREPARED_BUILD => $prepared,
    ]), [ConfigKey::PREPARE_SCHEMA => true]);
    $schema = (new SchemaFactory())($runtime);

    $entities = new EntityLocator();
    $entities->handle(new ClassInfo(PreparedSchemaEntity::class));
    $entities->finalize();
    $embeddings = new EmbeddingLocator();
    $embeddings->finalize();
    $file = $this->buildDirectory . '/schema.php';
    $command = new CommandTester(new GenerateSchemaCommand($databases, $entities, $embeddings));
    expect($command->execute(['--output' => $file]))->toBe(Command::SUCCESS);
    $config = require $file;

    expect($payload)->toBe(['schema' => $config[CycleConfigKey::ROOT][CycleConfigKey::SCHEMA]])
        ->and($schema->getRoles())->toBe(['prepared-schema'])
        ->and($schema->define('prepared-schema', SchemaInterface::ENTITY))->toBe(PreparedSchemaEntity::class)
        ->and($database->query('SELECT id, label, sentinel FROM prepared_schema_records')->fetchAll())->toBe([
            ['id' => 1, 'label' => 'keep', 'sentinel' => 'untouched'],
        ]);
});

it('retains explicit schema precedence including an intentional empty schema', function (?array $explicit): void {
    $store = new BuildStore($this->buildDirectory);
    $build = $store->begin();
    $build->write(ConfigKey::SCHEMA_ARTIFACT, ['schema' => cyclePreparedSchema()]);
    $prepared = $store->publish($build);
    $services = new CycleBuildContainer([AppConfigKey::PREPARED_BUILD => $prepared]);
    $value = cycleBuildContainerValue($services, [
        ConfigKey::PREPARE_SCHEMA => true,
        CycleConfigKey::ROOT => [CycleConfigKey::SCHEMA => $explicit],
    ]);

    $schema = (new SchemaFactory())($value);

    expect($schema->getRoles())->toBe(array_keys($explicit ?? []))
        ->and($services->requested)->toBe([]);
})->with([
    'empty' => [[]],
    'null follows native empty behavior' => [null],
    'application schema' => [[
        'application-schema' => [SchemaInterface::ENTITY => PreparedSchemaEntity::class],
    ]],
]);

it('preserves native source behavior when no prepared snapshot is selected by DI', function (): void {
    $composition = (new ConfigFactory())->create(
        new Environment([]),
        new ConfigProvider(),
        static fn (): array => [ConfigKey::PREPARE_SCHEMA => true],
    );
    $container = (new ContainerFactory())->create($composition->config, $composition->dependencies);

    expect($container->has(AppConfigKey::PREPARED_BUILD))->toBeFalse()
        ->and($container->get(SchemaInterface::class)->getRoles())->toBe([]);
});

it('does not load a prepared schema after the opt-in is disabled', function (): void {
    $store = new BuildStore($this->buildDirectory);
    $build = $store->begin();
    $build->write(ConfigKey::SCHEMA_ARTIFACT, ['schema' => cyclePreparedSchema()]);
    $services = new CycleBuildContainer([AppConfigKey::PREPARED_BUILD => $store->publish($build)]);

    $schema = (new SchemaFactory())(cycleBuildContainerValue($services));

    expect($schema->getRoles())->toBe([])->and($services->requested)->toBe([]);
});

it('loads prepared schema through the combined Cycle and application providers', function (): void {
    $store = new BuildStore($this->buildDirectory);
    $build = $store->begin();
    $build->write(ConfigKey::SCHEMA_ARTIFACT, ['schema' => cyclePreparedSchema()]);
    $prepared = $store->publish($build);
    $composition = (new ConfigFactory())->create(
        new Environment([]),
        new \Componenta\Cycle\ConfigProvider(),
        new ConfigProvider(),
        static fn (): array => [
            ConfigKey::PREPARE_SCHEMA => true,
            DependencyConfigKey::DEPENDENCIES => [
                DependencyConfigKey::SERVICES => [AppConfigKey::PREPARED_BUILD => $prepared],
            ],
        ],
    );
    $container = (new ContainerFactory())->create($composition->config, $composition->dependencies);

    expect($container->get(SchemaInterface::class)->getRoles())->toBe(['prepared-schema']);
});

it('falls back as a complete unit for missing incompatible or malformed artifacts', function (?array $payload, int $version): void {
    $store = new BuildStore($this->buildDirectory);
    $build = $store->begin();
    if ($payload !== null) {
        $build->write(ConfigKey::SCHEMA_ARTIFACT, $payload, $version);
    }
    $value = cycleBuildContainerValue(new CycleBuildContainer([
        AppConfigKey::PREPARED_BUILD => $store->publish($build),
    ]), [ConfigKey::PREPARE_SCHEMA => true]);

    expect((new SchemaFactory())($value)->getRoles())->toBe([]);
})->with([
    'missing' => [null, 1],
    'future format' => [['schema' => []], 2],
    'missing schema field' => [[], 1],
    'invalid schema type' => [['schema' => 'invalid'], 1],
    'partial valid and invalid roles' => [['schema' => ['valid' => [], 'invalid' => false]], 1],
]);

it('records nonportable schema as skipped instead of exporting runtime objects', function (): void {
    $builder = new SchemaBuilder(static fn (): array => ['runtime-object' => new \stdClass()]);
    $prepared = (new ApplicationBuildOrchestrator([$builder], new BuildStore($this->buildDirectory)))->build();

    expect($prepared->read(ConfigKey::SCHEMA_ARTIFACT))->toBeNull()
        ->and($prepared->skipped())->toBe([
            ConfigKey::SCHEMA_ARTIFACT => 'Cycle schema contains values that cannot be prepared; use explicit runtime schema configuration.',
        ]);
});

it('propagates compiler failures without publishing over the last usable generation', function (): void {
    $store = new BuildStore($this->buildDirectory);
    $first = $store->begin();
    $first->write(ConfigKey::SCHEMA_ARTIFACT, ['schema' => cyclePreparedSchema()]);
    $previous = $store->publish($first);
    $builder = new SchemaBuilder(static fn (): array => throw new \RuntimeException('Database metadata unavailable.'));

    expect(fn () => (new ApplicationBuildOrchestrator([$builder], $store))->build())
        ->toThrow(\RuntimeException::class, 'Database metadata unavailable.');
    expect($store->load()?->generation)->toBe($previous->generation)
        ->and($store->load()?->read(ConfigKey::SCHEMA_ARTIFACT))->toBe(['schema' => cyclePreparedSchema()]);
});

function cyclePreparedSchema(): array
{
    return ['prepared-schema' => [SchemaInterface::ENTITY => PreparedSchemaEntity::class]];
}

function cycleBuildContainerValue(CycleBuildContainer $container, array $config = []): ContainerValue
{
    return new ContainerValue($container, new Config($config, new Environment([])));
}

final class CycleBuildContainer implements ContainerInterface
{
    public array $requested = [];

    public function __construct(private array $services = []) {}

    public function get(string $id): mixed
    {
        $this->requested[] = $id;
        if (!array_key_exists($id, $this->services)) {
            throw new \LogicException('This operation must not resolve service ' . $id);
        }
        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->services);
    }
}

#[Entity(role: 'prepared-schema', table: 'prepared_schema_records')]
final class PreparedSchemaEntity
{
    #[Column(type: 'primary')]
    public int $id;

    #[Column(type: 'text')]
    public string $label;
}

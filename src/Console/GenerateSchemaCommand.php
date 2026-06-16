<?php

declare(strict_types=1);

namespace Componenta\Cycle\App\Console;

use Componenta\Cycle\App\Locator\EmbeddingLocator;
use Componenta\Cycle\App\Locator\EntityLocator;
use Componenta\Cycle\Mapper\LazyGhostMapper;
use Cycle\Annotated;
use Cycle\Database\DatabaseProviderInterface;
use Cycle\ORM\SchemaInterface;
use Cycle\Schema;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'db:schema',
    description: 'Generate and cache ORM schema to file',
)]
final class GenerateSchemaCommand extends Command
{
    public function __construct(
        private readonly DatabaseProviderInterface $dbal,
        private readonly EntityLocator $entityLocator,
        private readonly EmbeddingLocator $embeddingLocator,
    ) {
        parent::__construct();
    }

    private function createDefaults(): Schema\Defaults
    {
        return (new Schema\Defaults())->merge([
            SchemaInterface::MAPPER => LazyGhostMapper::class,
        ]);
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Output file path',
                getcwd() . '/config/autoload/cycle.local.php'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Generate ORM Schema');
        $io->text('Analyzing entity definitions...');

        $schema = $this->compileSchema();

        $outputPath = $input->getOption('output');
        $this->writeSchema($schema, $outputPath);

        $io->success(sprintf('Schema written to %s', $outputPath));

        $roles = array_keys($schema);
        $io->text(sprintf('Entities: %d', count($roles)));

        if ($output->isVerbose()) {
            $io->listing($roles);
        }

        return Command::SUCCESS;
    }

    private function compileSchema(): array
    {
        return (new Schema\Compiler())->compile(
            new Schema\Registry($this->dbal, $this->createDefaults()),
            [
                new Annotated\Embeddings($this->embeddingLocator),
                new Annotated\Entities($this->entityLocator),
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

    private function writeSchema(array $schema, string $path): void
    {
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $content = "<?php\n\n";
        $content .= "declare(strict_types=1);\n\n";
        $content .= "/**\n";
        $content .= " * Auto-generated Cycle ORM schema.\n";
        $content .= " * Generated at: " . date('Y-m-d H:i:s') . "\n";
        $content .= " *\n";
        $content .= " * Do not edit manually. Run `php console db:schema` to regenerate.\n";
        $content .= " */\n\n";
        $content .= "use Componenta\\Cycle\\ConfigKey;\n";
        $content .= "use Cycle\\ORM\\SchemaInterface;\n\n";
        $content .= "return [\n";
        $content .= "    ConfigKey::ROOT => [\n";
        $content .= "        ConfigKey::SCHEMA => " . $this->exportSchema($schema, 2) . ",\n";
        $content .= "    ],\n";
        $content .= "];\n";

        file_put_contents($path, $content);
    }

    private function exportSchema(array $schema, int $indent = 0): string
    {
        $pad = str_repeat('    ', $indent);
        $lines = ["["];

        foreach ($schema as $role => $definition) {
            $lines[] = $pad . "    " . $this->exportValue($role) . " => [";

            foreach ($definition as $key => $value) {
                $keyName = $this->schemaKeyToConstant($key);
                $lines[] = $pad . "        {$keyName} => " . $this->exportValue($value, $indent + 2) . ",";
            }

            $lines[] = $pad . "    ],";
        }

        $lines[] = $pad . "]";

        return implode("\n", $lines);
    }

    private function exportValue(mixed $value, int $indent = 0): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return "'" . addslashes($value) . "'";
        }

        if (is_array($value)) {
            if ($value === []) {
                return '[]';
            }

            $pad = str_repeat('    ', $indent);
            $isAssoc = array_keys($value) !== range(0, count($value) - 1);

            if (!$isAssoc && $this->isSimpleArray($value)) {
                $items = array_map(fn($v) => $this->exportValue($v), $value);
                return '[' . implode(', ', $items) . ']';
            }

            $lines = ["["];
            foreach ($value as $k => $v) {
                $exported = $this->exportValue($v, $indent + 1);
                if ($isAssoc) {
                    $lines[] = $pad . "    " . $this->exportValue($k) . " => {$exported},";
                } else {
                    $lines[] = $pad . "    {$exported},";
                }
            }
            $lines[] = $pad . "]";

            return implode("\n", $lines);
        }

        return var_export($value, true);
    }

    private function isSimpleArray(array $array): bool
    {
        if (count($array) > 5) {
            return false;
        }

        foreach ($array as $value) {
            if (!is_scalar($value) && $value !== null) {
                return false;
            }
        }

        return true;
    }

    private function schemaKeyToConstant(int $key): string
    {
        return match ($key) {
            SchemaInterface::ENTITY => 'SchemaInterface::ENTITY',
            SchemaInterface::MAPPER => 'SchemaInterface::MAPPER',
            SchemaInterface::SOURCE => 'SchemaInterface::SOURCE',
            SchemaInterface::REPOSITORY => 'SchemaInterface::REPOSITORY',
            SchemaInterface::DATABASE => 'SchemaInterface::DATABASE',
            SchemaInterface::TABLE => 'SchemaInterface::TABLE',
            SchemaInterface::PRIMARY_KEY => 'SchemaInterface::PRIMARY_KEY',
            SchemaInterface::FIND_BY_KEYS => 'SchemaInterface::FIND_BY_KEYS',
            SchemaInterface::COLUMNS => 'SchemaInterface::COLUMNS',
            SchemaInterface::RELATIONS => 'SchemaInterface::RELATIONS',
            SchemaInterface::CHILDREN => 'SchemaInterface::CHILDREN',
            SchemaInterface::SCOPE => 'SchemaInterface::SCOPE',
            SchemaInterface::TYPECAST => 'SchemaInterface::TYPECAST',
            SchemaInterface::SCHEMA => 'SchemaInterface::SCHEMA',
            SchemaInterface::TYPECAST_HANDLER => 'SchemaInterface::TYPECAST_HANDLER',
            SchemaInterface::GENERATED_FIELDS => 'SchemaInterface::GENERATED_FIELDS',
            default => (string) $key,
        };
    }
}
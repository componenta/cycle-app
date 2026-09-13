<?php

declare(strict_types=1);

namespace Componenta\Cycle\App\Console;

use Componenta\Cycle\App\Locator\EmbeddingLocator;
use Componenta\Cycle\App\Locator\EntityLocator;
use Componenta\Cycle\ConfigKey;
use Componenta\Cycle\Mapper\LazyGhostMapper;
use Componenta\VarExport\VarExport;
use Cycle\Annotated;
use Cycle\Database\DatabaseProviderInterface;
use Cycle\ORM\SchemaInterface;
use Cycle\Schema;
use ErrorException;
use RuntimeException;
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
        $expression = VarExport::withDefaults()->export([
            ConfigKey::ROOT => [ConfigKey::SCHEMA => $schema],
        ]);
        $content = "<?php\n\ndeclare(strict_types=1);\n\n";
        $content .= "/**\n * Auto-generated Cycle ORM schema.\n";
        $content .= " * Generated at: " . date('Y-m-d H:i:s') . "\n";
        $content .= " */\n\nreturn " . $expression . ";\n";

        $directory = dirname($path);
        $temporary = null;
        $stream = null;
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            if (!is_dir($directory) && !mkdir($directory, 0o755, true) && !is_dir($directory)) {
                throw new RuntimeException('Cannot create schema directory "' . $directory . '".');
            }
            $temporary = $directory . '/.' . basename($path) . '.' . bin2hex(random_bytes(12)) . '.tmp';
            $stream = fopen($temporary, 'xb');
            if ($stream === false || fwrite($stream, $content) !== strlen($content) || !fflush($stream)) {
                throw new RuntimeException('Cannot write complete schema "' . $path . '".');
            }
            fclose($stream);
            $stream = null;
            if (!rename($temporary, $path)) {
                throw new RuntimeException('Cannot publish schema "' . $path . '".');
            }
            $temporary = null;
            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($path, true);
            }
        } finally {
            try {
                if (is_resource($stream)) {
                    fclose($stream);
                }
                if ($temporary !== null && is_file($temporary)) {
                    unlink($temporary);
                }
            } finally {
                restore_error_handler();
            }
        }
    }
}
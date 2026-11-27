<?php

declare(strict_types=1);

namespace Componenta\Cycle\App\Console;

use Componenta\Cycle\App\Locator\EmbeddingLocator;
use Componenta\Cycle\App\Locator\EntityLocator;
use Componenta\Cycle\ConfigKey;
use Componenta\Cycle\App\SchemaCompiler;
use Componenta\VarExport\VarExport;
use Cycle\Database\DatabaseProviderInterface;
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
    private readonly SchemaCompiler $compiler;

    public function __construct(
        DatabaseProviderInterface $dbal,
        private readonly EntityLocator $entityLocator,
        private readonly EmbeddingLocator $embeddingLocator,
    ) {
        $this->compiler = new SchemaCompiler($dbal);
        parent::__construct();
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

        $schema = $this->compiler->compile($this->entityLocator, $this->embeddingLocator);

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
                try {
                    opcache_invalidate($path, true);
                } catch (ErrorException) {
                    // A restricted OPcache API does not make a completed publication fail.
                }
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

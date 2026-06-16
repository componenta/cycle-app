<?php

declare(strict_types=1);

namespace Componenta\Cycle\App\Console;

use Componenta\Cycle\App\Locator\EmbeddingLocator;
use Componenta\Cycle\App\Locator\EntityLocator;
use Componenta\Cycle\Mapper\LazyGhostMapper;
use Cycle\Annotated;
use Cycle\Database\DatabaseProviderInterface;
use Cycle\ORM\SchemaInterface;
use Cycle\Migrations\Migrator;
use Cycle\Schema;
use Cycle\Schema\Generator\Migrations\GenerateMigrations;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'db:generate',
    description: 'Generate migrations from entity definitions',
)]
final class GenerateCommand extends Command
{
    public function __construct(
        private readonly DatabaseProviderInterface $dbal,
        private readonly Migrator $migrator,
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
            ->addOption('run', 'r', InputOption::VALUE_NONE, 'Run generated migrations immediately');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Generate Migrations');
        $io->text('Analyzing entity definitions...');

        $this->migrator->configure();

        $beforeCount = count($this->migrator->getMigrations());

        $this->compileSchema();

        $afterCount = count($this->migrator->getMigrations());
        $generated = $afterCount - $beforeCount;

        if ($generated === 0) {
            $io->success('Schema is up to date. No migrations generated.');
            return Command::SUCCESS;
        }

        $io->success(sprintf('Generated %d migration(s).', $generated));

        $migrations = array_slice(
            iterator_to_array($this->migrator->getMigrations()),
            $beforeCount,
        );

        foreach ($migrations as $migration) {
            $io->writeln(sprintf(
                '  <info>+</info> %s',
                $migration->getState()->getName(),
            ));
        }

        if ($input->getOption('run')) {
            $io->newLine();
            $io->text('Running migrations...');

            $count = 0;
            while ($migration = $this->migrator->run()) {
                $io->writeln(sprintf(
                    '  <info>✓</info> %s',
                    $migration->getState()->getName(),
                ));
                $count++;
            }

            $io->newLine();
            $io->success(sprintf('Executed %d migration(s).', $count));
        }

        return Command::SUCCESS;
    }

    private function compileSchema(): void
    {
        new Schema\Compiler()->compile(
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
                new GenerateMigrations(
                    $this->migrator->getRepository(),
                    $this->migrator->getConfig(),
                ),
                new Schema\Generator\GenerateTypecast(),
            ],
        );
    }
}
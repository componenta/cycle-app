<?php

declare(strict_types=1);

namespace Componenta\Cycle\App\Console;

use Cycle\Migrations\Migrator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'db:rollback',
    description: 'Rollback last executed migration(s)',
)]
final class RollbackCommand extends Command
{
    public function __construct(
        private readonly Migrator $migrator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'all',
                'a',
                InputOption::VALUE_NONE,
                'Rollback all executed migrations',
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force execution without confirmation',
            )
            ->addOption(
                'schema',
                's',
                InputOption::VALUE_NONE,
                'Regenerate ORM schema after rollback',
            )
            ->addOption(
                'no-schema',
                null,
                InputOption::VALUE_NONE,
                'Skip schema regeneration prompt',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->migrator->configure();

        $io->title('Database Rollback');

        if (!$input->getOption('force')) {
            $warning = $input->getOption('all')
                ? 'This will rollback ALL migrations. Are you sure?'
                : 'This will rollback the last migration. Continue?';

            if (!$io->confirm($warning, false)) {
                $io->warning('Rollback cancelled.');
                return Command::SUCCESS;
            }
        }

        $count = 0;

        do {
            $migration = $this->migrator->rollback();

            if ($migration === null) {
                break;
            }

            $io->writeln(sprintf(
                '  <info>✓</info> Rolled back: <comment>%s</comment>',
                $migration->getState()->getName(),
            ));

            $count++;
        } while ($input->getOption('all'));

        $io->newLine();

        if ($count === 0) {
            $io->warning('Nothing to rollback.');
        } else {
            $io->success(sprintf('Rolled back %d migration(s).', $count));
            $this->handleSchemaGeneration($input, $output, $io);
        }

        return Command::SUCCESS;
    }

    private function handleSchemaGeneration(
        InputInterface $input,
        OutputInterface $output,
        SymfonyStyle $io,
    ): void {
        if ($input->getOption('no-schema')) {
            return;
        }

        $shouldGenerate = $input->getOption('schema')
            || $io->confirm('Regenerate ORM schema?', true);

        if (!$shouldGenerate) {
            return;
        }

        $application = $this->getApplication();

        if ($application === null) {
            $io->warning('Cannot regenerate schema: application not available.');
            return;
        }

        if (!$application->has('db:schema')) {
            $io->warning('Cannot regenerate schema: db:schema command not found.');
            return;
        }

        $io->newLine();

        $application->find('db:schema')->run(
            new ArrayInput([]),
            $output,
        );
    }
}
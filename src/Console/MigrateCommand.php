<?php

declare(strict_types=1);

namespace Componenta\Cycle\App\Console;

use Cycle\Migrations\Migrator;
use Cycle\Migrations\State;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'db:migrate',
    description: 'Execute pending database migrations',
)]
final class MigrateCommand extends Command
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
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force execution without confirmation in production',
            )
            ->addOption(
                'one',
                '1',
                InputOption::VALUE_NONE,
                'Execute only one migration',
            )
            ->addOption(
                'schema',
                's',
                InputOption::VALUE_NONE,
                'Regenerate ORM schema after migration',
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

        $pending = $this->getPendingMigrations();

        if ($pending === []) {
            $io->success('Nothing to migrate.');
            return Command::SUCCESS;
        }

        $io->title('Database Migration');
        $io->text(sprintf('Found <info>%d</info> pending migration(s):', count($pending)));
        $io->listing($pending);

        if (!$input->getOption('force') && !$io->confirm('Continue?', true)) {
            $io->warning('Migration cancelled.');
            return Command::SUCCESS;
        }

        $count = 0;

        do {
            $migration = $this->migrator->run();

            if ($migration === null) {
                break;
            }

            $state = $migration->getState();
            $io->writeln(sprintf(
                '  <info>✓</info> %s <comment>(%s)</comment>',
                $state->getName(),
                $this->formatStatus($state),
            ));

            $count++;
        } while (!$input->getOption('one'));

        $io->newLine();
        $io->success(sprintf('Executed %d migration(s).', $count));

        $this->handleSchemaGeneration($input, $output, $io);

        return Command::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function getPendingMigrations(): array
    {
        $pending = [];

        foreach ($this->migrator->getMigrations() as $migration) {
            $state = $migration->getState();

            if ($state->getStatus() !== State::STATUS_EXECUTED) {
                $pending[] = $state->getName();
            }
        }

        return $pending;
    }

    private function formatStatus(State $state): string
    {
        return match ($state->getStatus()) {
            State::STATUS_PENDING => 'pending',
            State::STATUS_EXECUTED => 'executed',
            default => 'unknown',
        };
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
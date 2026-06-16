<?php

declare(strict_types=1);

namespace Componenta\Cycle\App\Console;

use Cycle\Migrations\Migrator;
use Cycle\Migrations\State;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'db:status',
    description: 'Show database migrations status',
)]
final class StatusCommand extends Command
{
    public function __construct(
        private readonly Migrator $migrator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->migrator->configure();

        $io->title('Migration Status');

        $migrations = $this->migrator->getMigrations();

        if ($migrations === []) {
            $io->warning('No migrations found.');
            return Command::SUCCESS;
        }

        $rows = [];
        $pending = 0;
        $executed = 0;

        foreach ($migrations as $migration) {
            $state = $migration->getState();
            $status = $state->getStatus();

            if ($status === State::STATUS_EXECUTED) {
                $executed++;
                $statusLabel = '<info>Executed</info>';
            } else {
                $pending++;
                $statusLabel = '<comment>Pending</comment>';
            }

            $rows[] = [
                $state->getName(),
                $statusLabel,
                $state->getTimeCreated()?->format('Y-m-d H:i:s') ?? '-',
            ];
        }

        $io->table(
            ['Migration', 'Status', 'Created'],
            $rows,
        );

        $io->text([
            sprintf('Total: <info>%d</info>', count($migrations)),
            sprintf('Executed: <info>%d</info>', $executed),
            sprintf('Pending: <comment>%d</comment>', $pending),
        ]);

        return Command::SUCCESS;
    }
}

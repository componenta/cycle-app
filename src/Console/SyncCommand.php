<?php

declare(strict_types=1);

namespace Componenta\Cycle\App\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'db:sync',
    description: 'Generate and apply migrations, then regenerate ORM schema',
)]
final class SyncCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $application = $this->getApplication();

        if ($application === null) {
            $io->error('Application not available.');
            return Command::FAILURE;
        }

        $result = $application->find('db:generate')->run(
            new ArrayInput(['--run' => true]),
            $output,
        );

        if ($result !== Command::SUCCESS) {
            return $result;
        }

        return $application->find('db:schema')->run(
            new ArrayInput([]),
            $output,
        );
    }
}

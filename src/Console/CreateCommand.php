<?php

declare(strict_types=1);

namespace Componenta\Cycle\App\Console;

use Cycle\Migrations\Config\MigrationConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'db:create',
    description: 'Create a new empty migration file',
)]
final class CreateCommand extends Command
{
    public function __construct(
        private readonly MigrationConfig $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'Migration name (PascalCase, e.g., CreatePostsTable)',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $name */
        $name = $input->getArgument('name');

        if (!preg_match('/^[A-Z][a-zA-Z0-9]+$/', $name)) {
            $io->error('Migration name must be PascalCase (e.g., CreatePostsTable)');
            return Command::FAILURE;
        }

        $timestamp = date('YmdHis');
        $className = $name;
        $fileName = sprintf('%s_%s.php', $timestamp, $this->toSnakeCase($name));
        $filePath = rtrim($this->config->getDirectory(), '/') . '/' . $fileName;

        if (file_exists($filePath)) {
            $io->error(sprintf('Migration file already exists: %s', $fileName));
            return Command::FAILURE;
        }

        $namespace = $this->config->getNamespace() ?: 'Migration';
        $content = $this->generateMigrationContent($namespace, $className);

        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }

        file_put_contents($filePath, $content);

        $io->success(sprintf('Created migration: %s', $fileName));
        $io->text(sprintf('Path: <comment>%s</comment>', $filePath));

        return Command::SUCCESS;
    }

    private function generateMigrationContent(string $namespace, string $className): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Cycle\Migrations\Migration;

final class $className extends Migration
{
    protected const string DATABASE = 'default';

    public function up(): void
    {
        // \$this->table('example')
        //     ->addColumn('id', 'primary')
        //     ->addColumn('name', 'string', ['size' => 255])
        //     ->create();
    }

    public function down(): void
    {
        // \$this->table('example')->drop();
    }
}

PHP;
    }

    private function toSnakeCase(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }
}

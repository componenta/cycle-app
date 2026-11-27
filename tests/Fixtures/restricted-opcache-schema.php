<?php

declare(strict_types=1);

use Componenta\Cycle\App\Console\GenerateSchemaCommand;
use Componenta\Cycle\App\Locator\EmbeddingLocator;
use Componenta\Cycle\App\Locator\EntityLocator;
use Cycle\Database\DatabaseInterface;
use Cycle\Database\DatabaseProviderInterface;
use Symfony\Component\Console\Tester\CommandTester;

require $argv[1];

$restricted = false;
set_error_handler(static function (int $severity, string $message, string $file, int $line) use (&$restricted): bool {
    if ($severity === E_WARNING && str_contains($message, 'restrict_api')) {
        $restricted = true;
        return true;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
try {
    opcache_invalidate(__FILE__, true);
} finally {
    restore_error_handler();
}

$entities = new EntityLocator();
$entities->finalize();
$embeddings = new EmbeddingLocator();
$embeddings->finalize();
$databases = new class implements DatabaseProviderInterface {
    public function database(?string $database = null): DatabaseInterface
    {
        throw new LogicException('Empty discovery must not access a database.');
    }
};
$tester = new CommandTester(new GenerateSchemaCommand($databases, $entities, $embeddings));
$path = $argv[2] . '/schema.php';
$handler = static fn (): bool => true;
set_error_handler($handler);
try {
    $firstStatus = $tester->execute(['--output' => $path]);
    $firstConfig = require $path;
    file_put_contents($path, '<?php return ["previous" => true];');
    $secondStatus = $tester->execute(['--output' => $path]);
    $restored = set_error_handler(static fn (): bool => true);
    restore_error_handler();

    echo json_encode([
        'opcacheEnabled' => (bool) ini_get('opcache.enable') && (bool) ini_get('opcache.enable_cli'),
        'restrictionActive' => $restricted,
        'firstStatus' => $firstStatus,
        'firstConfig' => $firstConfig,
        'secondStatus' => $secondStatus,
        'secondConfig' => require $path,
        'replacementWritten' => !str_contains(file_get_contents($path), 'previous'),
        'handlerRestored' => $restored === $handler,
        'files' => scandir($argv[2]),
    ], JSON_THROW_ON_ERROR), "\n";
} finally {
    restore_error_handler();
}

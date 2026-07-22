<?php

declare(strict_types=1);

namespace Collector369\Console;

use Collector369\Collectors\CollectorManager;
use Collector369\Collectors\Providers\Investing\InvestingProvider;
use Collector369\Collectors\Storage\CollectorStorage;
use Collector369\Collectors\Validation\FileValidator;
use Collector369\Collectors\Workflow\WorkflowRunner;
use Collector369\Config\CollectorConfig;
use Collector369\Logging\Logger;

/**
 * CollectorConsole
 *
 * Interface de linha de comando do Collector369.
 * Ponto de entrada para execução de coletas via terminal.
 */
final class CollectorConsole
{
    public function __construct(private readonly string $rootPath)
    {
    }

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        $command = $argv[1] ?? null;

        if ($command !== 'collect:investing') {
            fwrite(STDERR, 'Uso: bin/collector369 collect:investing' . PHP_EOL);

            return 1;
        }

        $config = new CollectorConfig($this->rootPath);
        $logger = new Logger($config->path()->logs(), $config->logLevel());

        $fileValidator = new FileValidator();
        $storage = new CollectorStorage($config->outputPath());

        $provider = new InvestingProvider($config->investingIncomingPath());
        $workflow = new WorkflowRunner($provider, $fileValidator, $storage, $logger);
        $manager = new CollectorManager(['investing' => $workflow]);

        $result = $manager->run('investing');

        if ($result->success && $result->file !== null) {
            echo 'Coleta concluída com sucesso.' . PHP_EOL;
            echo "Arquivo: {$result->file->path}" . PHP_EOL;

            return 0;
        }

        fwrite(STDERR, "Falha na coleta: {$result->error}" . PHP_EOL);

        return 1;
    }
}

<?php

declare(strict_types=1);

namespace Collector369\Console;

use Collector369\Collectors\CollectorManager;
use Collector369\Collectors\Providers\Investing\InvestingProvider;
use Collector369\Collectors\Providers\TwelveData\TwelveDataProvider;
use Collector369\Collectors\ProviderRegistry;
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
 *
 * O comando é sempre `collect:<provider>`, resolvido diretamente pelo
 * ProviderRegistry — adicionar um novo Provider no futuro significa
 * apenas registrá-lo aqui; nenhuma alteração em CollectorManager ou
 * WorkflowRunner é necessária.
 */
final class CollectorConsole
{
    /** Símbolos validados como suportados pelo Twelve Data (Sprint de pesquisa). */
    private const TWELVE_DATA_SYMBOLS = [
        'VALE', 'PBR', 'EWZ', 'XLF', 'XLP', 'XLE', 'XME', 'EEM', 'SOXX',
        'USD/MXN', 'USD/NOK', 'USD/NZD', 'USD/AUD', 'USD/KRW', 'USD/CNY', 'EUR/BRL',
        'XAU/USD', 'SOYB',
    ];

    public function __construct(private readonly string $rootPath)
    {
    }

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        $command = $argv[1] ?? null;

        if ($command === null || !str_starts_with($command, 'collect:')) {
            fwrite(STDERR, 'Uso: bin/collector369 collect:<provider>' . PHP_EOL);

            return 1;
        }

        $providerName = substr($command, strlen('collect:'));

        $config = new CollectorConfig($this->rootPath);
        $logger = new Logger($config->path()->logs(), $config->logLevel());
        $fileValidator = new FileValidator();
        $storage = new CollectorStorage($config->outputPath());

        $registry = new ProviderRegistry();
        $registry->register('investing', new InvestingProvider($config->investingIncomingPath()));
        $registry->register('twelvedata', new TwelveDataProvider(
            $config->twelveDataApiKey(),
            self::TWELVE_DATA_SYMBOLS,
            $config->twelveDataStagingPath(),
        ));

        if (!$registry->has($providerName)) {
            fwrite(STDERR, "Provider não registrado: {$providerName}" . PHP_EOL);

            return 1;
        }

        $manager = new CollectorManager($this->buildWorkflows($registry, $fileValidator, $storage, $logger));
        $result = $manager->run($providerName);

        if ($result->success && $result->file !== null) {
            echo 'Coleta concluída com sucesso.' . PHP_EOL;
            echo "Arquivo: {$result->file->path}" . PHP_EOL;

            return 0;
        }

        fwrite(STDERR, "Falha na coleta: {$result->error}" . PHP_EOL);

        return 1;
    }

    /**
     * @return array<string, WorkflowRunner>
     */
    private function buildWorkflows(
        ProviderRegistry $registry,
        FileValidator $validator,
        CollectorStorage $storage,
        Logger $logger,
    ): array {
        $workflows = [];

        foreach ($registry->names() as $name) {
            $workflows[$name] = new WorkflowRunner($registry->get($name), $validator, $storage, $logger);
        }

        return $workflows;
    }
}

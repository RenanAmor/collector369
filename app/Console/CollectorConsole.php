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
use Collector369\Transport\DTO\TransportRunOutcome;
use Collector369\Transport\Ftp\NativeFtpClient;
use Collector369\Transport\ProductionTransport;

/**
 * CollectorConsole
 *
 * Interface de linha de comando do Collector369.
 * Ponto de entrada para execução de coletas e para o transporte do
 * arquivo de output até a produção, via terminal.
 *
 * O comando de coleta é sempre `collect:<provider>`, resolvido diretamente
 * pelo ProviderRegistry — adicionar um novo Provider no futuro significa
 * apenas registrá-lo aqui; nenhuma alteração em CollectorManager ou
 * WorkflowRunner é necessária.
 *
 * O comando de transporte é `transport:production [--provider=<nome>]`,
 * deliberadamente separado do `collect:` — coleta e entrega para produção
 * são passos conscientes e independentes, nunca encadeados automaticamente.
 */
final class CollectorConsole
{
    /** Símbolos validados como suportados pelo Twelve Data (Sprint de pesquisa). */
    private const TWELVE_DATA_SYMBOLS = [
        'VALE', 'PBR', 'EWZ', 'XLF', 'XLP', 'XLE', 'XME', 'EEM', 'SOXX',
        'USD/MXN', 'USD/NOK', 'USD/NZD', 'USD/AUD', 'USD/KRW', 'USD/CNY', 'EUR/BRL',
        'XAU/USD', 'SOYB',
    ];

    private const USAGE = 'Uso: bin/collector369 collect:<provider> | transport:production [--provider=<nome>]';

    public function __construct(private readonly string $rootPath)
    {
    }

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        $command = $argv[1] ?? null;

        if ($command !== null && str_starts_with($command, 'collect:')) {
            return $this->runCollect(substr($command, strlen('collect:')));
        }

        if ($command === 'transport:production') {
            return $this->runTransport($argv);
        }

        fwrite(STDERR, self::USAGE . PHP_EOL);

        return 1;
    }

    private function runCollect(string $providerName): int
    {
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

    /**
     * @param list<string> $argv
     */
    private function runTransport(array $argv): int
    {
        $config = new CollectorConfig($this->rootPath);
        $logger = new Logger($config->path()->logs(), $config->logLevel());

        $host = $config->productionFtpHost();
        $user = $config->productionFtpUser();
        $password = $config->productionFtpPassword();

        if ($host === '' || $user === '' || $password === '') {
            fwrite(STDERR, 'Faltam PRODUCTION_FTP_HOST / PRODUCTION_FTP_USER / PRODUCTION_FTP_PASSWORD no .env.' . PHP_EOL);

            return 2;
        }

        $transport = new ProductionTransport(
            outputPath: $config->outputPath(),
            ftp: new NativeFtpClient(),
            host: $host,
            port: $config->productionFtpPort(),
            user: $user,
            password: $password,
            remoteRoot: $config->productionFtpRemotePath(),
            logger: $logger,
        );

        $outcome = $transport->run($this->extractProviderFlag($argv));

        return $this->reportTransportResults($outcome);
    }

    /**
     * @param list<string> $argv
     */
    private function extractProviderFlag(array $argv): ?string
    {
        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--provider=')) {
                return substr($arg, strlen('--provider='));
            }
        }

        return null;
    }

    private function reportTransportResults(TransportRunOutcome $outcome): int
    {
        if (!$outcome->connected) {
            fwrite(STDERR, 'Falha ao conectar/autenticar no FTP de produção — nenhum provider foi processado.' . PHP_EOL);

            return 2;
        }

        if ($outcome->results === []) {
            echo 'Nada para transportar (nenhum arquivo em OUTPUT_PATH).' . PHP_EOL;

            return 0;
        }

        $hasError = false;

        foreach ($outcome->results as $result) {
            $stream = $result->status === 'error' || $result->status === 'conflict' ? STDERR : STDOUT;
            fwrite($stream, "[{$result->provider}] {$result->status}: {$result->message}" . PHP_EOL);

            if ($result->status === 'error' || $result->status === 'conflict') {
                $hasError = true;
            }
        }

        return $hasError ? 1 : 0;
    }
}

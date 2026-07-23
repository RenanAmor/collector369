<?php

declare(strict_types=1);

namespace Collector369\Transport;

use Collector369\Logging\Logger;
use Collector369\Transport\DTO\ProviderTransportResult;
use Collector369\Transport\DTO\TransportRunOutcome;
use Collector369\Transport\Ftp\FtpClientInterface;

/**
 * ProductionTransport
 *
 * Transporta o arquivo mais recente de cada provider de
 * OUTPUT_PATH/{provider}/ até a raiz FTP restrita de produção
 * (storage/collector369/output/{provider}/), sem depender de nenhuma
 * das classes protegidas do núcleo de coleta.
 *
 * Não se recupera sozinho de um redeploy da Hostinger que apague os
 * arquivos remotos: a restauração só acontece na próxima vez que este
 * comando for executado manualmente.
 */
final class ProductionTransport
{
    private const TMP_PREFIX = '.tmp_';

    /**
     * @param array<int, int> $retryDelaysSeconds espera (segundos) entre tentativas de conexão; a quantidade de tentativas é count($retryDelaysSeconds) + 1
     */
    public function __construct(
        private readonly string $outputPath,
        private readonly FtpClientInterface $ftp,
        private readonly string $host,
        private readonly int $port,
        private readonly string $user,
        private readonly string $password,
        private readonly string $remoteRoot,
        private readonly Logger $logger,
        private readonly int $connectTimeoutSeconds = 10,
        private readonly array $retryDelaysSeconds = [2, 5],
    ) {
    }

    public function run(?string $onlyProvider = null): TransportRunOutcome
    {
        $providers = $this->discoverProviders($onlyProvider);

        if ($providers === []) {
            $this->logger->info('transport: nenhum provider com arquivos em OUTPUT_PATH');

            return new TransportRunOutcome(true, []);
        }

        if (!$this->connectWithRetry()) {
            $this->logger->error('transport: não foi possível conectar/autenticar via FTPS após as tentativas configuradas');

            return new TransportRunOutcome(false, []);
        }

        $results = [];
        foreach ($providers as $provider) {
            $localFile = $this->findLatestLocalFile($provider);
            $results[] = $localFile === null
                ? ProviderTransportResult::nothingToTransport($provider)
                : $this->transferOne($provider, $localFile);
        }

        $this->ftp->close();

        return new TransportRunOutcome(true, $results);
    }

    private function connectWithRetry(): bool
    {
        $maxAttempts = count($this->retryDelaysSeconds) + 1;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            if ($attempt > 1) {
                $delay = $this->retryDelaysSeconds[$attempt - 2];
                $this->logger->warning('transport: nova tentativa de conexão FTPS', [
                    'tentativa' => $attempt,
                    'espera_segundos' => $delay,
                ]);
                sleep($delay);
            }

            if ($this->ftp->connect($this->host, $this->port, $this->connectTimeoutSeconds)
                && $this->ftp->login($this->user, $this->password)
            ) {
                return true;
            }

            $this->ftp->close();
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function discoverProviders(?string $onlyProvider): array
    {
        if (!is_dir($this->outputPath)) {
            return [];
        }

        $entries = scandir($this->outputPath) ?: [];
        $providers = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (!is_dir($this->outputPath . '/' . $entry)) {
                continue;
            }

            if ($onlyProvider !== null && $entry !== $onlyProvider) {
                continue;
            }

            $providers[] = $entry;
        }

        sort($providers);

        return $providers;
    }

    private function findLatestLocalFile(string $provider): ?string
    {
        $files = glob($this->outputPath . '/' . $provider . '/*') ?: [];
        $files = array_values(array_filter($files, 'is_file'));

        if ($files === []) {
            return null;
        }

        // Nomes seguem {provider}_{Y-m-d_His}.{ext} (CollectorStorage) — ordem
        // lexicográfica coincide com ordem cronológica.
        sort($files);

        return end($files);
    }

    private function transferOne(string $provider, string $localFile): ProviderTransportResult
    {
        $filename = basename($localFile);
        $remoteDir = rtrim($this->remoteRoot, '/') . '/' . $provider;
        $remoteFinal = $remoteDir . '/' . $filename;
        $remoteTmp = $remoteDir . '/' . self::TMP_PREFIX . $filename;

        $this->ftp->mkdir($remoteDir);

        $this->cleanupOrphanTmp($provider, $remoteTmp);

        $localSize = filesize($localFile);
        $remoteSize = $this->ftp->size($remoteFinal);

        if ($remoteSize >= 0) {
            return $this->resolveExistingRemoteFile($provider, $localFile, $remoteFinal, $localSize, $remoteSize);
        }

        return $this->uploadViaTemporaryFile($provider, $localFile, $remoteTmp, $remoteFinal, $localSize);
    }

    /**
     * Ajuste 3: remove um .tmp órfão do mesmo arquivo deixado por uma
     * tentativa anterior interrompida, antes de tentar de novo.
     */
    private function cleanupOrphanTmp(string $provider, string $remoteTmp): void
    {
        if ($this->ftp->size($remoteTmp) < 0) {
            return;
        }

        $this->logger->warning('transport: removendo .tmp órfão de execução anterior', [
            'provider' => $provider,
            'arquivo_remoto' => $remoteTmp,
        ]);

        $this->ftp->delete($remoteTmp);
    }

    /**
     * Ajuste 1 e 2: mesmo nome remoto já existe — só é considerado "já
     * transportado" após comparação byte a byte; conteúdo diferente (mesmo
     * com tamanho igual) é conflito, nunca sobrescrito silenciosamente.
     */
    private function resolveExistingRemoteFile(
        string $provider,
        string $localFile,
        string $remoteFinal,
        int $localSize,
        int $remoteSize,
    ): ProviderTransportResult {
        if ($remoteSize === $localSize && $this->contentsMatch($localFile, $remoteFinal)) {
            $this->logger->info('transport: produção já está atualizada', [
                'provider' => $provider,
                'arquivo' => basename($localFile),
            ]);

            return ProviderTransportResult::alreadyCurrent($provider, $localFile, $remoteFinal);
        }

        $message = "Conflito: '{$remoteFinal}' já existe em produção com conteúdo diferente do arquivo local mais recente. Upload abortado — nada foi sobrescrito.";
        $this->logger->error('transport: conflito de arquivo', [
            'provider' => $provider,
            'arquivo_remoto' => $remoteFinal,
        ]);

        return ProviderTransportResult::conflict($provider, $localFile, $remoteFinal, $message);
    }

    private function uploadViaTemporaryFile(
        string $provider,
        string $localFile,
        string $remoteTmp,
        string $remoteFinal,
        int $localSize,
    ): ProviderTransportResult {
        if (!$this->ftp->put($localFile, $remoteTmp)) {
            $message = "Falha ao enviar arquivo para {$remoteTmp} (ftp_put).";
            $this->logger->error('transport: falha de upload', ['provider' => $provider, 'destino' => $remoteTmp]);

            return ProviderTransportResult::error($provider, $message);
        }

        $tmpSize = $this->ftp->size($remoteTmp);
        if ($tmpSize !== $localSize || !$this->contentsMatch($localFile, $remoteTmp)) {
            $this->ftp->delete($remoteTmp);
            $message = "Falha de integridade ao validar upload de " . basename($localFile) . " para {$provider} — arquivo temporário removido, nada foi publicado.";
            $this->logger->error('transport: falha de integridade pós-upload', ['provider' => $provider]);

            return ProviderTransportResult::error($provider, $message);
        }

        if (!$this->ftp->rename($remoteTmp, $remoteFinal)) {
            $message = "Falha ao renomear arquivo temporário para o nome final ({$remoteFinal}).";
            $this->logger->error('transport: falha ao finalizar rename', ['provider' => $provider, 'destino' => $remoteFinal]);

            return ProviderTransportResult::error($provider, $message);
        }

        $this->logger->info('transport: arquivo transportado com sucesso', [
            'provider' => $provider,
            'arquivo' => basename($localFile),
            'bytes' => $localSize,
        ]);

        return ProviderTransportResult::transported($provider, $localFile, $remoteFinal, $localSize);
    }

    private function contentsMatch(string $localFile, string $remotePath): bool
    {
        $downloaded = tempnam(sys_get_temp_dir(), 'c369-transport-check-');
        if ($downloaded === false) {
            return false;
        }

        $ok = $this->ftp->get($remotePath, $downloaded);
        $match = $ok && hash_file('sha256', $downloaded) === hash_file('sha256', $localFile);

        unlink($downloaded);

        return $match;
    }
}

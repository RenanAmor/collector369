<?php

declare(strict_types=1);

namespace Collector369\Collectors\Providers\Investing;

use Collector369\Collectors\Contracts\CollectorProviderInterface;
use Collector369\Collectors\DTO\CollectedFile;
use Collector369\Collectors\Exceptions\CollectorException;
use DateTimeImmutable;

/**
 * InvestingProvider
 *
 * Provider de coleta do Investing.com.
 *
 * Estratégia adotada: a planilha da Carteira é exportada manualmente e
 * depositada em uma pasta de entrada monitorada. Este Provider localiza o
 * arquivo mais recente dessa pasta e o entrega ao restante do pipeline
 * (validação e armazenamento). Nenhuma automação de navegador é executada
 * aqui — ver docs/arquitetura/Comparativo-Automacao-Investing.md para o
 * histórico da decisão.
 */
final class InvestingProvider implements CollectorProviderInterface
{
    private const PROVIDER_NAME = 'investing';

    public function __construct(private readonly string $incomingPath)
    {
    }

    public function collect(): CollectedFile
    {
        if (!is_dir($this->incomingPath)) {
            throw new CollectorException("Pasta de entrada não encontrada: {$this->incomingPath}");
        }

        $latestFile = $this->findMostRecentFile($this->incomingPath);

        if ($latestFile === null) {
            throw new CollectorException(
                "Nenhuma planilha encontrada em {$this->incomingPath}. Deposite a planilha exportada da Carteira nessa pasta antes de rodar a coleta.",
            );
        }

        return new CollectedFile(
            path: $latestFile,
            provider: self::PROVIDER_NAME,
            collectedAt: new DateTimeImmutable(),
            originalFilename: basename($latestFile),
        );
    }

    private function findMostRecentFile(string $directory): ?string
    {
        $latestFile = null;
        $latestModifiedAt = -1;

        foreach (scandir($directory) as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '.gitkeep') {
                continue;
            }

            $path = $directory . '/' . $entry;

            if (!is_file($path)) {
                continue;
            }

            $modifiedAt = filemtime($path);

            if ($modifiedAt !== false && $modifiedAt > $latestModifiedAt) {
                $latestModifiedAt = $modifiedAt;
                $latestFile = $path;
            }
        }

        return $latestFile;
    }
}

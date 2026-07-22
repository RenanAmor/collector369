<?php

declare(strict_types=1);

namespace Collector369\Collectors\Downloads;

use Collector369\Collectors\DTO\CollectedFile;
use Collector369\Collectors\Exceptions\CollectorException;
use Collector369\Support\Filesystem;
use DateTimeImmutable;

/**
 * DownloadManager
 *
 * Gerenciador de downloads do Collector369.
 * Responsável por capturar, organizar e rastrear arquivos baixados durante a coleta.
 */
final class DownloadManager
{
    /**
     * @param array{filePath: ?string, timestamp: ?string} $result resultado retornado pelo BrowserManager
     */
    public function capture(string $provider, array $result): CollectedFile
    {
        $path = $result['filePath'] ?? null;

        if (!is_string($path) || $path === '' || !Filesystem::exists($path)) {
            throw new CollectorException('Arquivo baixado não encontrado: ' . ($path ?? '(vazio)'));
        }

        return new CollectedFile(
            path: $path,
            provider: $provider,
            collectedAt: new DateTimeImmutable($result['timestamp'] ?? 'now'),
            originalFilename: basename($path),
        );
    }
}

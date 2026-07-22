<?php

declare(strict_types=1);

namespace Collector369\Collectors\Storage;

use Collector369\Collectors\DTO\CollectedFile;
use Collector369\Support\Filesystem;

/**
 * CollectorStorage
 *
 * Camada de armazenamento do Collector369.
 * Gerencia persistência de arquivos coletados no sistema de arquivos local.
 *
 * Move o arquivo já validado da área de staging para o diretório oficial
 * de saída, de onde o Laboratório 369 poderá futuramente consumi-lo.
 */
final class CollectorStorage
{
    public function __construct(private readonly string $outputPath)
    {
    }

    public function store(CollectedFile $file): CollectedFile
    {
        $extension = pathinfo($file->path, PATHINFO_EXTENSION);
        $filename = sprintf('%s_%s.%s', $file->provider, $file->collectedAt->format('Y-m-d_His'), $extension);
        $destination = rtrim($this->outputPath, '/') . '/' . $file->provider . '/' . $filename;

        Filesystem::move($file->path, $destination);

        return new CollectedFile(
            path: $destination,
            provider: $file->provider,
            collectedAt: $file->collectedAt,
            originalFilename: $file->originalFilename,
        );
    }
}

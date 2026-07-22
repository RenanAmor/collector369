<?php

declare(strict_types=1);

namespace Collector369\Support;

use Collector369\Collectors\Exceptions\CollectorException;

/**
 * Filesystem
 *
 * Utilitário para operações de sistema de arquivos do Collector369.
 * Abstrai leitura, escrita, movimentação e verificação de arquivos.
 */
final class Filesystem
{
    public static function exists(string $path): bool
    {
        return file_exists($path);
    }

    public static function size(string $path): int
    {
        $size = @filesize($path);

        if ($size === false) {
            throw new CollectorException("Não foi possível determinar o tamanho do arquivo: {$path}");
        }

        return $size;
    }

    public static function ensureDirectoryExists(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new CollectorException("Não foi possível criar o diretório: {$directory}");
        }
    }

    public static function move(string $from, string $to): void
    {
        self::ensureDirectoryExists(dirname($to));

        if (!rename($from, $to)) {
            throw new CollectorException("Não foi possível mover o arquivo de {$from} para {$to}");
        }
    }
}

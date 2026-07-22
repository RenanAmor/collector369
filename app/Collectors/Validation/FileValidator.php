<?php

declare(strict_types=1);

namespace Collector369\Collectors\Validation;

use Collector369\Collectors\DTO\CollectedFile;
use Collector369\Collectors\Exceptions\CollectorException;
use Collector369\Support\Filesystem;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * FileValidator
 *
 * Validador de arquivos coletados.
 * Verifica integridade, formato e consistência dos dados após o download.
 *
 * Escopo estritamente técnico (existência, tamanho, extensão e leitura
 * estrutural da planilha) — nenhuma regra de negócio/pontuação é avaliada
 * aqui, conforme os limites do Collector369 definidos no Documento Mestre.
 */
final class FileValidator
{
    private const ALLOWED_EXTENSIONS = ['xlsx', 'xls', 'csv'];
    private const MINIMUM_ROWS = 2;

    public function validate(CollectedFile $file): void
    {
        if (!Filesystem::exists($file->path)) {
            throw new CollectorException("Arquivo coletado não existe: {$file->path}");
        }

        if (Filesystem::size($file->path) <= 0) {
            throw new CollectorException("Arquivo coletado está vazio: {$file->path}");
        }

        $extension = strtolower(pathinfo($file->path, PATHINFO_EXTENSION));

        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new CollectorException("Extensão de arquivo não permitida ({$extension}): {$file->path}");
        }

        $this->validateStructure($file->path);
    }

    private function validateStructure(string $path): void
    {
        try {
            $spreadsheet = IOFactory::load($path);
        } catch (Throwable $exception) {
            throw new CollectorException(
                "Arquivo corrompido ou ilegível como planilha: {$path}. {$exception->getMessage()}",
                previous: $exception,
            );
        }

        $rowCount = $spreadsheet->getActiveSheet()->getHighestDataRow();

        if ($rowCount < self::MINIMUM_ROWS) {
            throw new CollectorException("Planilha coletada tem menos linhas que o esperado ({$rowCount}): {$path}");
        }
    }
}

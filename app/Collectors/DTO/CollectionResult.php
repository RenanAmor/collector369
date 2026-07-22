<?php

declare(strict_types=1);

namespace Collector369\Collectors\DTO;

/**
 * CollectionResult
 *
 * Data Transfer Object que representa o resultado de uma coleta.
 * Contém status, arquivos coletados, erros e métricas da execução.
 */
final class CollectionResult
{
    private function __construct(
        public readonly bool $success,
        public readonly ?CollectedFile $file,
        public readonly ?string $error,
        public readonly float $durationMs,
    ) {
    }

    public static function success(CollectedFile $file, float $durationMs): self
    {
        return new self(true, $file, null, $durationMs);
    }

    public static function failure(string $error, float $durationMs): self
    {
        return new self(false, null, $error, $durationMs);
    }
}

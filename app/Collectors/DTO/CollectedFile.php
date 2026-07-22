<?php

declare(strict_types=1);

namespace Collector369\Collectors\DTO;

use DateTimeImmutable;

/**
 * CollectedFile
 *
 * Data Transfer Object que representa um arquivo coletado.
 * Encapsula metadados como caminho, origem, timestamp e status.
 */
final class CollectedFile
{
    public function __construct(
        public readonly string $path,
        public readonly string $provider,
        public readonly DateTimeImmutable $collectedAt,
        public readonly string $originalFilename,
    ) {
    }
}

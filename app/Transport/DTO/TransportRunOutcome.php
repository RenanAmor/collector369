<?php

declare(strict_types=1);

namespace Collector369\Transport\DTO;

/**
 * TransportRunOutcome
 *
 * Resultado agregado de uma execução de ProductionTransport::run().
 * `connected` distingue "não deu nem para conectar/autenticar" (falha
 * total, nenhum provider processado) de uma execução normal em que cada
 * provider tem seu próprio resultado individual.
 */
final class TransportRunOutcome
{
    /**
     * @param list<ProviderTransportResult> $results
     */
    public function __construct(
        public readonly bool $connected,
        public readonly array $results,
    ) {
    }
}

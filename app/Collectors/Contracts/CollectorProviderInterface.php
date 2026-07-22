<?php

declare(strict_types=1);

namespace Collector369\Collectors\Contracts;

use Collector369\Collectors\DTO\CollectedFile;

/**
 * CollectorProviderInterface
 *
 * Contrato que todos os Providers de coleta devem implementar.
 * Define a interface padrão para extração de dados de qualquer fonte.
 */
interface CollectorProviderInterface
{
    public function collect(): CollectedFile;
}

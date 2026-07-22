<?php

declare(strict_types=1);

namespace Collector369\Collectors;

use Collector369\Collectors\DTO\CollectionResult;
use Collector369\Collectors\Exceptions\CollectorException;
use Collector369\Collectors\Workflow\WorkflowRunner;

/**
 * CollectorManager
 *
 * Gerenciador central de coleta do Collector369.
 * Responsável por orquestrar os Providers e coordenar o fluxo de coleta.
 */
final class CollectorManager
{
    /**
     * @param array<string, WorkflowRunner> $workflows workflows registrados, indexados pelo nome do provider
     */
    public function __construct(private readonly array $workflows)
    {
    }

    public function run(string $provider): CollectionResult
    {
        if (!isset($this->workflows[$provider])) {
            throw new CollectorException("Provider não registrado: {$provider}");
        }

        return $this->workflows[$provider]->run();
    }
}

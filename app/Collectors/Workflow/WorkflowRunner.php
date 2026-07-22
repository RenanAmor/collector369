<?php

declare(strict_types=1);

namespace Collector369\Collectors\Workflow;

use Collector369\Collectors\Contracts\CollectorProviderInterface;
use Collector369\Collectors\DTO\CollectionResult;
use Collector369\Collectors\Exceptions\CollectorException;
use Collector369\Collectors\Storage\CollectorStorage;
use Collector369\Collectors\Validation\FileValidator;
use Collector369\Logging\Logger;

/**
 * WorkflowRunner
 *
 * Executor de workflows de coleta.
 * Coordena a sequência de etapas de um fluxo completo de coleta de dados:
 * extração (Provider) → validação (FileValidator) → armazenamento (CollectorStorage).
 */
final class WorkflowRunner
{
    public function __construct(
        private readonly CollectorProviderInterface $provider,
        private readonly FileValidator $validator,
        private readonly CollectorStorage $storage,
        private readonly Logger $logger,
    ) {
    }

    public function run(): CollectionResult
    {
        $start = microtime(true);

        try {
            $this->logger->info('Iniciando workflow de coleta');

            $collectedFile = $this->provider->collect();
            $this->validator->validate($collectedFile);
            $storedFile = $this->storage->store($collectedFile);

            $this->logger->info('Workflow de coleta concluído', ['arquivo' => $storedFile->path]);

            return CollectionResult::success($storedFile, $this->elapsedMs($start));
        } catch (CollectorException $exception) {
            $this->logger->error('Falha no workflow de coleta', ['erro' => $exception->getMessage()]);

            return CollectionResult::failure($exception->getMessage(), $this->elapsedMs($start));
        }
    }

    private function elapsedMs(float $start): float
    {
        return round((microtime(true) - $start) * 1000, 2);
    }
}

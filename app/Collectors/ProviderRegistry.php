<?php

declare(strict_types=1);

namespace Collector369\Collectors;

use Collector369\Collectors\Contracts\CollectorProviderInterface;
use Collector369\Collectors\Exceptions\CollectorException;

/**
 * ProviderRegistry
 *
 * Registro central de Providers de coleta do Collector369.
 * Permite que novos Providers sejam adicionados ao sistema apenas
 * registrando uma instância aqui, sem qualquer alteração no núcleo
 * (CollectorProviderInterface, WorkflowRunner e CollectorManager
 * permanecem intocados).
 */
final class ProviderRegistry
{
    /** @var array<string, CollectorProviderInterface> */
    private array $providers = [];

    public function register(string $name, CollectorProviderInterface $provider): void
    {
        if (isset($this->providers[$name])) {
            throw new CollectorException("Provider já registrado: {$name}");
        }

        $this->providers[$name] = $provider;
    }

    public function get(string $name): CollectorProviderInterface
    {
        if (!isset($this->providers[$name])) {
            throw new CollectorException("Provider não registrado: {$name}");
        }

        return $this->providers[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->providers[$name]);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->providers);
    }
}

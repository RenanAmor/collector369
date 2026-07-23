<?php

declare(strict_types=1);

namespace Collector369\Collectors;

use Collector369\Collectors\Exceptions\CollectorException;

/**
 * ProviderResolver
 *
 * Determina qual Provider deve atender um determinado ativo, a partir de
 * um mapeamento recebido como configuração (ativo => nome do Provider).
 *
 * Não interpreta, valida ou decide sobre o ativo em si — apenas roteia
 * para o Provider indicado na configuração recebida. A existência,
 * inclusão ou exclusão de ativos permanece fora da responsabilidade do
 * Collector369.
 */
final class ProviderResolver
{
    /**
     * @param array<string, string> $assetProviderMap mapeamento ativo => nome do Provider
     */
    public function __construct(private readonly array $assetProviderMap)
    {
    }

    public function resolve(string $asset): string
    {
        if (!isset($this->assetProviderMap[$asset])) {
            throw new CollectorException("Nenhum Provider configurado para o ativo: {$asset}");
        }

        return $this->assetProviderMap[$asset];
    }
}

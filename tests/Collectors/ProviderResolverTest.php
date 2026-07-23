<?php

declare(strict_types=1);

namespace Tests\Collectors;

use Collector369\Collectors\Exceptions\CollectorException;
use Collector369\Collectors\ProviderResolver;
use PHPUnit\Framework\TestCase;

final class ProviderResolverTest extends TestCase
{
    public function testResolvesTheProviderConfiguredForAnAsset(): void
    {
        $resolver = new ProviderResolver([
            'ATIVO_A' => 'provider-a',
            'ATIVO_B' => 'provider-b',
        ]);

        self::assertSame('provider-a', $resolver->resolve('ATIVO_A'));
        self::assertSame('provider-b', $resolver->resolve('ATIVO_B'));
    }

    public function testThrowsWhenAssetHasNoProviderConfigured(): void
    {
        $resolver = new ProviderResolver(['ATIVO_A' => 'provider-a']);

        $this->expectException(CollectorException::class);

        $resolver->resolve('ATIVO_DESCONHECIDO');
    }

    public function testWorksWithAnEmptyMap(): void
    {
        $resolver = new ProviderResolver([]);

        $this->expectException(CollectorException::class);

        $resolver->resolve('QUALQUER_ATIVO');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Collectors;

use Collector369\Collectors\Contracts\CollectorProviderInterface;
use Collector369\Collectors\DTO\CollectedFile;
use Collector369\Collectors\Exceptions\CollectorException;
use Collector369\Collectors\ProviderRegistry;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ProviderRegistryTest extends TestCase
{
    public function testRegistersAndRetrievesAProviderByName(): void
    {
        $registry = new ProviderRegistry();
        $provider = $this->fakeProvider();

        $registry->register('exemplo', $provider);

        self::assertTrue($registry->has('exemplo'));
        self::assertSame($provider, $registry->get('exemplo'));
        self::assertSame(['exemplo'], $registry->names());
    }

    public function testSupportsMultipleProvidersSimultaneously(): void
    {
        $registry = new ProviderRegistry();

        $registry->register('primeiro', $this->fakeProvider());
        $registry->register('segundo', $this->fakeProvider());

        self::assertSame(['primeiro', 'segundo'], $registry->names());
    }

    public function testThrowsWhenRegisteringDuplicateName(): void
    {
        $registry = new ProviderRegistry();
        $registry->register('exemplo', $this->fakeProvider());

        $this->expectException(CollectorException::class);

        $registry->register('exemplo', $this->fakeProvider());
    }

    public function testThrowsWhenGettingUnregisteredProvider(): void
    {
        $registry = new ProviderRegistry();

        $this->expectException(CollectorException::class);

        $registry->get('inexistente');
    }

    public function testHasReturnsFalseForUnregisteredProvider(): void
    {
        $registry = new ProviderRegistry();

        self::assertFalse($registry->has('inexistente'));
    }

    private function fakeProvider(): CollectorProviderInterface
    {
        return new class implements CollectorProviderInterface {
            public function collect(): CollectedFile
            {
                return new CollectedFile(
                    path: '/tmp/fake.xlsx',
                    provider: 'fake',
                    collectedAt: new DateTimeImmutable(),
                    originalFilename: 'fake.xlsx',
                );
            }
        };
    }
}

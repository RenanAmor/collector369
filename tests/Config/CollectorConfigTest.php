<?php

declare(strict_types=1);

namespace Tests\Config;

use Collector369\Config\CollectorConfig;
use PHPUnit\Framework\TestCase;
use Tests\Concerns\InteractsWithTempDirectories;

final class CollectorConfigTest extends TestCase
{
    use InteractsWithTempDirectories;

    private const ENV_KEYS = [
        'INVESTING_BASE_URL',
        'INVESTING_CARTEIRA_URL',
        'INVESTING_PROFILE_DIR',
        'INVESTING_INCOMING_PATH',
        'OUTPUT_PATH',
        'BROWSER_HEADLESS',
        'BROWSER_TIMEOUT',
        'LOG_LEVEL',
        'STORAGE_PATH',
    ];

    private string $root;

    protected function setUp(): void
    {
        $this->resetEnv();
        $this->root = $this->makeTempDirectory('collector369-config');
        mkdir($this->root . '/storage', 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
        $this->resetEnv();
    }

    public function testReadsValuesFromEnvFile(): void
    {
        file_put_contents($this->root . '/.env', implode("\n", [
            'INVESTING_CARTEIRA_URL=https://br.investing.com/portfolio/example',
            'INVESTING_PROFILE_DIR=./storage/session/investing-profile',
            'INVESTING_INCOMING_PATH=./storage/incoming/investing',
            'OUTPUT_PATH=./storage/output',
            'BROWSER_HEADLESS=false',
            'BROWSER_TIMEOUT=15000',
            'LOG_LEVEL=info',
        ]));

        $config = new CollectorConfig($this->root);

        self::assertSame('https://br.investing.com/portfolio/example', $config->investingCarteiraUrl());
        self::assertSame($this->root . '/storage/session/investing-profile', $config->investingProfileDir());
        self::assertSame($this->root . '/storage/incoming/investing', $config->investingIncomingPath());
        self::assertSame($this->root . '/storage/output', $config->outputPath());
        self::assertFalse($config->browserHeadless());
        self::assertSame(15000, $config->browserTimeout());
        self::assertSame('info', $config->logLevel());
    }

    public function testFallsBackToDefaultsWhenEnvHasNoValues(): void
    {
        file_put_contents($this->root . '/.env', '');

        $config = new CollectorConfig($this->root);

        self::assertSame('', $config->investingBaseUrl());
        self::assertTrue($config->browserHeadless());
        self::assertSame(30000, $config->browserTimeout());
        self::assertSame('debug', $config->logLevel());
    }

    private function resetEnv(): void
    {
        foreach (self::ENV_KEYS as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }
}

<?php

declare(strict_types=1);

namespace Collector369\Config;

use Collector369\Support\Path;
use Dotenv\Dotenv;

/**
 * CollectorConfig
 *
 * Gerenciador de configurações do Collector369.
 * Centraliza acesso a parâmetros de ambiente, providers e comportamento do framework.
 */
final class CollectorConfig
{
    private readonly Path $path;

    public function __construct(private readonly string $rootPath)
    {
        Dotenv::createImmutable($this->rootPath)->safeLoad();

        $this->path = new Path($this->resolvePath($this->env('STORAGE_PATH', './storage')));
    }

    public function path(): Path
    {
        return $this->path;
    }

    public function investingBaseUrl(): string
    {
        return $this->env('INVESTING_BASE_URL', '');
    }

    public function investingCarteiraUrl(): string
    {
        return $this->env('INVESTING_CARTEIRA_URL', '');
    }

    public function investingProfileDir(): string
    {
        return $this->resolvePath($this->env('INVESTING_PROFILE_DIR', './storage/session/investing-profile'));
    }

    public function outputPath(): string
    {
        return $this->resolvePath($this->env('OUTPUT_PATH', './storage/output'));
    }

    public function browserHeadless(): bool
    {
        return filter_var($this->env('BROWSER_HEADLESS', 'true'), FILTER_VALIDATE_BOOLEAN);
    }

    public function browserTimeout(): int
    {
        return (int) $this->env('BROWSER_TIMEOUT', '30000');
    }

    public function logLevel(): string
    {
        return $this->env('LOG_LEVEL', 'debug');
    }

    private function env(string $key, string $default): string
    {
        $value = $_ENV[$key] ?? getenv($key);

        return $value === false || $value === null || $value === '' ? $default : (string) $value;
    }

    private function resolvePath(string $path): string
    {
        $resolved = str_starts_with($path, '.') ? $this->rootPath . '/' . ltrim($path, './') : $path;

        return str_replace('\\', '/', $resolved);
    }
}

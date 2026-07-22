<?php

declare(strict_types=1);

namespace Collector369\Logging;

use Collector369\Support\Filesystem;

/**
 * Logger
 *
 * Sistema de logging do Collector369.
 * Registra eventos, erros e métricas de todas as operações de coleta.
 */
final class Logger
{
    private const LEVELS = [
        'debug' => 0,
        'info' => 1,
        'warning' => 2,
        'error' => 3,
    ];

    public function __construct(
        private readonly string $directory,
        private readonly string $minLevel = 'debug',
    ) {
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    private function log(string $level, string $message, array $context): void
    {
        if ((self::LEVELS[$level] ?? 0) < (self::LEVELS[$this->minLevel] ?? 0)) {
            return;
        }

        Filesystem::ensureDirectoryExists($this->directory);

        $line = sprintf(
            "[%s] %s: %s%s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $context === [] ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE),
        );

        file_put_contents($this->directory . '/collector-' . date('Y-m-d') . '.log', $line, FILE_APPEND);
    }
}

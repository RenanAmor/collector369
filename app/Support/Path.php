<?php

declare(strict_types=1);

namespace Collector369\Support;

/**
 * Path
 *
 * Utilitário para manipulação de caminhos do Collector369.
 * Resolve e normaliza paths de storage, downloads e cache.
 */
final class Path
{
    private readonly string $root;

    public function __construct(string $root)
    {
        $normalized = rtrim(str_replace('\\', '/', $root), '/');
        $resolved = realpath($normalized);

        $this->root = $resolved !== false ? str_replace('\\', '/', $resolved) : $normalized;
    }

    public function root(): string
    {
        return $this->root;
    }

    public function join(string ...$segments): string
    {
        $clean = array_map(
            static fn (string $segment): string => trim(str_replace('\\', '/', $segment), '/'),
            array_filter($segments, static fn (string $segment): bool => $segment !== ''),
        );

        return $clean === [] ? $this->root : $this->root . '/' . implode('/', $clean);
    }

    public function downloads(string ...$segments): string
    {
        return $this->join('downloads', ...$segments);
    }

    public function output(string ...$segments): string
    {
        return $this->join('output', ...$segments);
    }

    public function session(string ...$segments): string
    {
        return $this->join('session', ...$segments);
    }

    public function logs(string ...$segments): string
    {
        return $this->join('logs', ...$segments);
    }

    public function cache(string ...$segments): string
    {
        return $this->join('cache', ...$segments);
    }

    public function temp(string ...$segments): string
    {
        return $this->join('temp', ...$segments);
    }
}

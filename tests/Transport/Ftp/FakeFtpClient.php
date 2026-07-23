<?php

declare(strict_types=1);

namespace Tests\Transport\Ftp;

use Collector369\Transport\Ftp\FtpClientInterface;

/**
 * FakeFtpClient
 *
 * Implementação em memória de FtpClientInterface para testar
 * ProductionTransport sem rede real.
 */
final class FakeFtpClient implements FtpClientInterface
{
    /** @var array<string, string> caminho remoto => conteúdo */
    public array $files = [];

    public int $connectAttempts = 0;

    public int $putCalls = 0;

    /** @var list<string> */
    public array $deletedPaths = [];

    private bool $connected = false;

    public function __construct(
        private readonly int $connectSucceedsOnAttempt = 1,
        private readonly bool $corruptNextPut = false,
        private readonly bool $failRename = false,
    ) {
    }

    public function connect(string $host, int $port, int $timeoutSeconds): bool
    {
        $this->connectAttempts++;

        if ($this->connectAttempts < $this->connectSucceedsOnAttempt) {
            return false;
        }

        $this->connected = true;

        return true;
    }

    public function login(string $user, string $password): bool
    {
        return $this->connected;
    }

    public function mkdir(string $path): bool
    {
        return true;
    }

    public function size(string $path): int
    {
        return isset($this->files[$path]) ? strlen($this->files[$path]) : -1;
    }

    public function put(string $localPath, string $remotePath): bool
    {
        $this->putCalls++;

        $content = file_get_contents($localPath);
        if ($content === false) {
            return false;
        }

        $this->files[$remotePath] = $this->corruptNextPut ? $content . 'CORROMPIDO' : $content;

        return true;
    }

    public function get(string $remotePath, string $localPath): bool
    {
        if (!isset($this->files[$remotePath])) {
            return false;
        }

        return file_put_contents($localPath, $this->files[$remotePath]) !== false;
    }

    public function rename(string $from, string $to): bool
    {
        if ($this->failRename || !isset($this->files[$from])) {
            return false;
        }

        $this->files[$to] = $this->files[$from];
        unset($this->files[$from]);

        return true;
    }

    public function delete(string $path): bool
    {
        if (!isset($this->files[$path])) {
            return false;
        }

        unset($this->files[$path]);
        $this->deletedPaths[] = $path;

        return true;
    }

    public function close(): void
    {
        $this->connected = false;
    }
}

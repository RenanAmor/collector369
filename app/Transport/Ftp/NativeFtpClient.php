<?php

declare(strict_types=1);

namespace Collector369\Transport\Ftp;

/**
 * NativeFtpClient
 *
 * Implementação real de FtpClientInterface via extensão ftp_* do PHP.
 * Usa exclusivamente FTPS explícito (ftp_ssl_connect) — nunca cai para
 * FTP sem criptografia, mesmo comportamento já validado em
 * bin/ftp-upload-test.php durante a descoberta da Sprint 14.
 */
final class NativeFtpClient implements FtpClientInterface
{
    /** @var resource|null */
    private $connection = null;

    public function connect(string $host, int $port, int $timeoutSeconds): bool
    {
        if (!function_exists('ftp_ssl_connect')) {
            return false;
        }

        $conn = @ftp_ssl_connect($host, $port, $timeoutSeconds);
        if ($conn === false) {
            return false;
        }

        $this->connection = $conn;

        return true;
    }

    public function login(string $user, string $password): bool
    {
        if ($this->connection === null) {
            return false;
        }

        if (!@ftp_login($this->connection, $user, $password)) {
            return false;
        }

        ftp_pasv($this->connection, true);

        return true;
    }

    public function mkdir(string $path): bool
    {
        if ($this->connection === null) {
            return false;
        }

        return @ftp_mkdir($this->connection, $path) !== false;
    }

    public function size(string $path): int
    {
        if ($this->connection === null) {
            return -1;
        }

        return @ftp_size($this->connection, $path);
    }

    public function put(string $localPath, string $remotePath): bool
    {
        if ($this->connection === null) {
            return false;
        }

        return @ftp_put($this->connection, $remotePath, $localPath, FTP_BINARY);
    }

    public function get(string $remotePath, string $localPath): bool
    {
        if ($this->connection === null) {
            return false;
        }

        return @ftp_get($this->connection, $localPath, $remotePath, FTP_BINARY);
    }

    public function rename(string $from, string $to): bool
    {
        if ($this->connection === null) {
            return false;
        }

        return @ftp_rename($this->connection, $from, $to);
    }

    public function delete(string $path): bool
    {
        if ($this->connection === null) {
            return false;
        }

        return @ftp_delete($this->connection, $path);
    }

    public function close(): void
    {
        if ($this->connection !== null) {
            @ftp_close($this->connection);
            $this->connection = null;
        }
    }
}

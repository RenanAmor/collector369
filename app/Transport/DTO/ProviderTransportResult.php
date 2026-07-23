<?php

declare(strict_types=1);

namespace Collector369\Transport\DTO;

/**
 * ProviderTransportResult
 *
 * Resultado do transporte de um provider individual para produção.
 *
 * status possíveis:
 * - transported: arquivo enviado e verificado com sucesso.
 * - already_current: produção já tinha o mesmo conteúdo (idempotente).
 * - nothing_to_transport: provider não tem nenhum arquivo local.
 * - conflict: já existe arquivo remoto com o mesmo nome e conteúdo
 *   diferente — upload abortado para não sobrescrever silenciosamente.
 * - error: falha técnica (conexão, integridade pós-upload, rename etc.).
 */
final class ProviderTransportResult
{
    private function __construct(
        public readonly string $provider,
        public readonly string $status,
        public readonly string $message,
        public readonly ?string $localFile = null,
        public readonly ?string $remoteFile = null,
        public readonly ?int $bytes = null,
    ) {
    }

    public static function transported(string $provider, string $localFile, string $remoteFile, int $bytes): self
    {
        return new self(
            $provider,
            'transported',
            "Arquivo transportado e verificado com sucesso ({$bytes} bytes).",
            $localFile,
            $remoteFile,
            $bytes,
        );
    }

    public static function alreadyCurrent(string $provider, string $localFile, string $remoteFile): self
    {
        return new self(
            $provider,
            'already_current',
            'Produção já está atualizada com o conteúdo do arquivo mais recente.',
            $localFile,
            $remoteFile,
        );
    }

    public static function nothingToTransport(string $provider): self
    {
        return new self($provider, 'nothing_to_transport', 'Nenhum arquivo local encontrado para este provider.');
    }

    public static function conflict(string $provider, string $localFile, string $remoteFile, string $message): self
    {
        return new self($provider, 'conflict', $message, $localFile, $remoteFile);
    }

    public static function error(string $provider, string $message): self
    {
        return new self($provider, 'error', $message);
    }
}

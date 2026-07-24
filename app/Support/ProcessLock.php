<?php

declare(strict_types=1);

namespace Collector369\Support;

/**
 * ProcessLock
 *
 * Exclusão mútua entre processos baseada em flock() sobre um arquivo.
 * Usada para impedir que dois ciclos do mesmo fluxo rodem sobrepostos
 * (ex.: dois ciclos de coleta simultâneos disputando o limite de
 * créditos/minuto de uma API externa). Se o processo que detém o lock
 * travar ou morrer, o sistema operacional libera o lock automaticamente
 * — não é necessário nenhum arquivo de PID nem limpeza manual.
 */
final class ProcessLock
{
    /** @var resource|null */
    private $handle;

    public function __construct(private readonly string $lockPath)
    {
    }

    /**
     * Tenta adquirir o lock sem bloquear.
     *
     * @return bool false se outro processo já detém o lock
     */
    public function acquire(): bool
    {
        Filesystem::ensureDirectoryExists(dirname($this->lockPath));

        $handle = fopen($this->lockPath, 'c');

        if ($handle === false) {
            return false;
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return false;
        }

        $this->handle = $handle;

        return true;
    }

    public function release(): void
    {
        if ($this->handle === null) {
            return;
        }

        flock($this->handle, LOCK_UN);
        fclose($this->handle);
        $this->handle = null;
    }
}

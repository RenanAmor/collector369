<?php

declare(strict_types=1);

namespace Collector369\Collectors\Browser;

use Collector369\Collectors\Exceptions\CollectorException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * BrowserManager
 *
 * Gerenciador de instâncias de navegador para coleta via Playwright.
 * Controla ciclo de vida, configuração e pool de browsers.
 *
 * A automação real do navegador roda em um script Node/Playwright separado
 * (ver diretório automation/); este gerenciador invoca esse script como
 * subprocesso e interpreta o resultado JSON retornado em stdout.
 */
final class BrowserManager
{
    public function __construct(
        private readonly string $rootPath,
        private readonly int $timeoutMs,
    ) {
    }

    /**
     * @return array{success: bool, filePath: ?string, timestamp: ?string, error: ?string}
     */
    public function runScript(string $relativeScriptPath): array
    {
        $process = new Process(['node', $relativeScriptPath], $this->rootPath, null, null, $this->timeoutMs / 1000);

        try {
            $process->run();
        } catch (Throwable $exception) {
            throw new CollectorException(
                "Falha ao executar script de automação: {$relativeScriptPath}. {$exception->getMessage()}",
                previous: $exception,
            );
        }

        $output = trim($process->getOutput());
        $decoded = $output === '' ? null : json_decode($output, true);

        if (!is_array($decoded)) {
            throw new CollectorException(
                "Script de automação não retornou um resultado JSON válido: {$relativeScriptPath}. Stderr: " . trim($process->getErrorOutput()),
            );
        }

        if (($decoded['success'] ?? false) !== true) {
            throw new CollectorException($decoded['error'] ?? 'Falha desconhecida na automação do navegador.');
        }

        return $decoded;
    }
}

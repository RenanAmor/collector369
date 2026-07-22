<?php

declare(strict_types=1);

namespace Collector369\Collectors\Providers\Investing;

use Collector369\Collectors\Browser\BrowserManager;
use Collector369\Collectors\Contracts\CollectorProviderInterface;
use Collector369\Collectors\DTO\CollectedFile;
use Collector369\Collectors\Downloads\DownloadManager;

/**
 * InvestingProvider
 *
 * Provider de coleta do Investing.com.
 * Aciona o script de automação (login via sessão salva, navegação até a
 * Carteira e exportação) e captura o arquivo baixado.
 */
final class InvestingProvider implements CollectorProviderInterface
{
    private const PROVIDER_NAME = 'investing';
    private const EXPORT_SCRIPT = 'automation/investing/export.js';

    public function __construct(
        private readonly BrowserManager $browserManager,
        private readonly DownloadManager $downloadManager,
    ) {
    }

    public function collect(): CollectedFile
    {
        $result = $this->browserManager->runScript(self::EXPORT_SCRIPT);

        return $this->downloadManager->capture(self::PROVIDER_NAME, $result);
    }
}

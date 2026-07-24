<?php

declare(strict_types=1);

namespace Collector369\Collectors\Providers\B3;

use Collector369\Collectors\Exceptions\CollectorException;
use DateTimeImmutable;
use Symfony\Component\Process\Process;

/**
 * B3BulletinClient
 *
 * Localiza e baixa o Boletim Diário do Mercado da B3 (arquivo "BDI_00",
 * capítulo Derivativos/Ações) e extrai o texto da tabela de negócios
 * consolidados via `pdftotext -table` (ver B3BulletinTableParser para o
 * porquê do modo `-table`).
 *
 * A B3 não publica boletim nos fins de semana/feriados nem antes do
 * fechamento do pregão do dia — por isso a resolução da data anda para
 * trás a partir da data de referência até encontrar o boletim publicado
 * mais recente (máximo MAX_LOOKBACK_DAYS dias), em vez de assumir que o
 * boletim de "hoje" sempre existe. `resolveLatestBulletinDate()` só faz
 * requisições HEAD (baratas); o download completo (~40MB) só acontece
 * quando o chamador decide de fato buscar o conteúdo (permite caching por
 * data no Provider sem baixar o arquivo à toa a cada ciclo de 5 minutos).
 */
final class B3BulletinClient
{
    private const BASE_URL = 'https://arquivos.b3.com.br/bdi/download/bdi';
    private const FILE_NUMBER = '00';
    private const MAX_LOOKBACK_DAYS = 7;
    private const USER_AGENT = 'Mozilla/5.0 (Collector369)';

    /** @var callable(string): bool */
    private $urlExists;

    /** @var callable(string, string): void */
    private $download;

    /** @var callable(string): string */
    private $extractTable;

    /**
     * @param (callable(string): bool)|null $urlExists substituível em testes
     * @param (callable(string, string): void)|null $download substituível em testes
     * @param (callable(string): string)|null $extractTable substituível em testes
     */
    public function __construct(
        ?callable $urlExists = null,
        ?callable $download = null,
        ?callable $extractTable = null,
    ) {
        $this->urlExists = $urlExists ?? self::defaultUrlExists();
        $this->download = $download ?? self::defaultDownload();
        $this->extractTable = $extractTable ?? self::defaultExtractTable();
    }

    /**
     * @return array{date: DateTimeImmutable, delayed: bool, url: string}
     */
    public function resolveLatestBulletinDate(DateTimeImmutable $referenceDate): array
    {
        $candidate = $referenceDate;

        for ($i = 0; $i <= self::MAX_LOOKBACK_DAYS; $i++) {
            $url = $this->urlFor($candidate);

            if (($this->urlExists)($url)) {
                return [
                    'date' => $candidate,
                    'delayed' => $candidate->format('Y-m-d') !== $referenceDate->format('Y-m-d'),
                    'url' => $url,
                ];
            }

            $candidate = $candidate->modify('-1 day');
        }

        throw new CollectorException(
            'Não foi possível localizar o Boletim Diário do Mercado da B3 nos últimos ' . self::MAX_LOOKBACK_DAYS . ' dias.'
        );
    }

    public function downloadAndExtractTable(string $url): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'b3bdi') . '.pdf';

        try {
            ($this->download)($url, $tmpPath);

            return ($this->extractTable)($tmpPath);
        } finally {
            if (file_exists($tmpPath)) {
                unlink($tmpPath);
            }
        }
    }

    private function urlFor(DateTimeImmutable $date): string
    {
        return sprintf(
            '%s/%s/BDI_%s_%s.pdf',
            self::BASE_URL,
            $date->format('Y-m-d'),
            self::FILE_NUMBER,
            $date->format('Ymd'),
        );
    }

    /**
     * O servidor de arquivos da B3 responde 405 (Method Not Allowed) a
     * requisições HEAD — confirmado ao vivo, não documentado por eles —
     * então a checagem de existência precisa ser um GET de verdade. Para
     * não baixar os ~40MB só para confirmar que o arquivo existe, a
     * transferência é abortada assim que os cabeçalhos de resposta chegam
     * (CURLOPT_HEADERFUNCTION retornando 0 interrompe o curl nesse ponto).
     */
    private static function defaultUrlExists(): callable
    {
        return static function (string $url): bool {
            $status = null;

            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_TIMEOUT, 15);
            curl_setopt($curl, CURLOPT_USERAGENT, self::USER_AGENT);
            curl_setopt($curl, CURLOPT_HEADERFUNCTION, static function ($handle, string $header) use (&$status): int {
                if ($status === null && preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
                    $status = (int) $matches[1];

                    return 0;
                }

                return strlen($header);
            });
            curl_exec($curl);
            curl_close($curl);

            return $status === 200;
        };
    }

    private static function defaultDownload(): callable
    {
        return static function (string $url, string $destPath): void {
            $fp = fopen($destPath, 'wb');

            if ($fp === false) {
                throw new CollectorException("Não foi possível criar arquivo temporário para o boletim B3: {$destPath}");
            }

            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_FILE, $fp);
            curl_setopt($curl, CURLOPT_TIMEOUT, 180);
            curl_setopt($curl, CURLOPT_USERAGENT, self::USER_AGENT);
            $success = curl_exec($curl);
            $error = curl_error($curl);
            $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            fclose($fp);

            if ($success !== true || $status !== 200) {
                @unlink($destPath);

                throw new CollectorException("Falha ao baixar o Boletim Diário do Mercado da B3 ({$url}): HTTP {$status} {$error}");
            }
        };
    }

    private static function defaultExtractTable(): callable
    {
        return static function (string $pdfPath): string {
            $process = new Process(['pdftotext', '-table', $pdfPath, '-']);
            $process->setTimeout(180);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new CollectorException('Falha ao extrair texto do boletim B3 via pdftotext: ' . $process->getErrorOutput());
            }

            return $process->getOutput();
        };
    }
}

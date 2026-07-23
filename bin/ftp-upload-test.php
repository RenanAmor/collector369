#!/usr/bin/env php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Collector369\Config\CollectorConfig;

$config = new CollectorConfig(dirname(__DIR__));

$host = $config->productionFtpHost();
$port = $config->productionFtpPort();
$user = $config->productionFtpUser();
$password = $config->productionFtpPassword();
$remotePath = $config->productionFtpRemotePath();

if ($host === '' || $user === '' || $password === '') {
    fwrite(STDERR, "Faltam PRODUCTION_FTP_HOST / PRODUCTION_FTP_USER / PRODUCTION_FTP_PASSWORD no .env local.\n");
    exit(1);
}

$remoteFileName = 'collector369-transport-test-' . date('Ymd_His') . '.txt';
$remoteFilePath = rtrim($remotePath, '/') . '/' . $remoteFileName;

$localContent = "Collector369 - teste controlado de transporte (Sprint 14)\n"
    . "Gerado em: " . date('c') . "\n";

$localFile = tempnam(sys_get_temp_dir(), 'c369-ftp-test-');
if ($localFile === false) {
    fwrite(STDERR, "Falha ao criar arquivo temporário local.\n");
    exit(1);
}
file_put_contents($localFile, $localContent);

echo "== Teste de upload FTPS (controlado, com limpeza automática) ==\n";
echo "Host: {$host}:{$port}\n";
echo "Usuário: {$user}\n";
echo "Arquivo remoto de teste: {$remoteFilePath}\n\n";

$ok = runUploadTest($host, $port, $user, $password, $localFile, $remoteFilePath, $localContent);

unlink($localFile);

exit($ok ? 0 : 1);

function runUploadTest(
    string $host,
    int $port,
    string $user,
    string $password,
    string $localFile,
    string $remoteFilePath,
    string $expectedContent
): bool {
    if (!function_exists('ftp_ssl_connect')) {
        fwrite(STDERR, "Extensão FTP sem suporte a SSL — abortando (não usar FTP sem criptografia para escrita real).\n");
        return false;
    }

    $conn = @ftp_ssl_connect($host, $port, 10);
    if ($conn === false) {
        fwrite(STDERR, "Falha ao abrir conexão via FTPS explícito.\n");
        return false;
    }

    if (!@ftp_login($conn, $user, $password)) {
        fwrite(STDERR, "Falha no login via FTPS.\n");
        ftp_close($conn);
        return false;
    }

    ftp_pasv($conn, true);

    echo "--> Enviando arquivo de teste...\n";
    if (!ftp_put($conn, $remoteFilePath, $localFile, FTP_BINARY)) {
        fwrite(STDERR, "    Falha no upload (ftp_put).\n");
        ftp_close($conn);
        return false;
    }
    echo "    Upload concluído.\n";

    echo "--> Verificando existência e tamanho remoto...\n";
    $remoteSize = ftp_size($conn, $remoteFilePath);
    $localSize = filesize($localFile);
    if ($remoteSize === -1) {
        fwrite(STDERR, "    Arquivo remoto não encontrado após upload.\n");
        ftp_close($conn);
        return false;
    }
    if ($remoteSize !== $localSize) {
        fwrite(STDERR, "    Tamanho divergente (local: {$localSize}, remoto: {$remoteSize}).\n");
        ftp_close($conn);
        return false;
    }
    echo "    Tamanho confere ({$remoteSize} bytes).\n";

    echo "--> Baixando de volta para validar integridade do conteúdo...\n";
    $downloadedFile = tempnam(sys_get_temp_dir(), 'c369-ftp-check-');
    $downloadOk = $downloadedFile !== false && ftp_get($conn, $downloadedFile, $remoteFilePath, FTP_BINARY);
    $contentOk = $downloadOk && file_get_contents($downloadedFile) === $expectedContent;
    if ($downloadedFile !== false) {
        unlink($downloadedFile);
    }

    if (!$contentOk) {
        fwrite(STDERR, "    Conteúdo baixado não confere com o original.\n");
    } else {
        echo "    Conteúdo confere byte a byte.\n";
    }

    echo "--> Removendo arquivo de teste da produção (limpeza)...\n";
    $deleteOk = @ftp_delete($conn, $remoteFilePath);
    if (!$deleteOk) {
        fwrite(STDERR, "    AVISO: não foi possível remover o arquivo de teste remoto — remover manualmente: {$remoteFilePath}\n");
    } else {
        echo "    Arquivo de teste removido com sucesso.\n";
    }

    ftp_close($conn);
    echo "\n";

    return $contentOk && $deleteOk;
}

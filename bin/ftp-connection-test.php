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

echo "== Teste de conexão FTP (somente leitura, não destrutivo) ==\n";
echo "Host: {$host}:{$port}\n";
echo "Usuário: {$user}\n";
echo "Diretório remoto esperado: {$remotePath}\n\n";

$ok = testExplicitFtps($host, $port, $user, $password)
    ?? testPlainFtp($host, $port, $user, $password);

exit($ok ? 0 : 1);

function testExplicitFtps(string $host, int $port, string $user, string $password): ?bool
{
    echo "--> Tentando FTPS explícito (ftp_ssl_connect)...\n";

    if (!function_exists('ftp_ssl_connect')) {
        echo "    Indisponível: a extensão FTP deste PHP não foi compilada com suporte a SSL.\n\n";
        return null;
    }

    $conn = @ftp_ssl_connect($host, $port, 10);
    if ($conn === false) {
        echo "    Falha ao abrir conexão via FTPS explícito.\n\n";
        return null;
    }

    return finishHandshake($conn, $user, $password, 'FTPS explícito');
}

function testPlainFtp(string $host, int $port, string $user, string $password): bool
{
    echo "--> FTPS explícito não confirmado. Testando FTP sem criptografia (apenas diagnóstico)...\n";
    echo "    AVISO: credenciais trafegariam em texto claro nesse modo — não usar para transporte real sem revisar antes.\n";

    $conn = @ftp_connect($host, $port, 10);
    if ($conn === false) {
        echo "    Falha ao abrir conexão via FTP simples.\n";
        return false;
    }

    return finishHandshake($conn, $user, $password, 'FTP sem criptografia');
}

/**
 * @param resource $conn
 */
function finishHandshake($conn, string $user, string $password, string $label): bool
{
    if (!@ftp_login($conn, $user, $password)) {
        echo "    Falha no login ({$label}).\n\n";
        ftp_close($conn);
        return false;
    }

    ftp_pasv($conn, true);

    $pwd = ftp_pwd($conn);
    $listing = ftp_nlist($conn, '.');

    echo "    Conectado e autenticado via {$label}.\n";
    echo "    Diretório atual no servidor: " . ($pwd !== false ? $pwd : '(desconhecido)') . "\n";
    echo "    Conteúdo listado (" . ($listing !== false ? count($listing) : 0) . " itens):\n";

    foreach ($listing ?: [] as $item) {
        echo "      - {$item}\n";
    }

    ftp_close($conn);
    echo "\n";

    return true;
}

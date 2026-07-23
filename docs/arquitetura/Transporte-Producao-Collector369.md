# Transporte de Produção do Collector369 (Sprint 14)

## 1. Contexto

A Sprint 13 fixou o caminho canônico de output (`Config::outputPath()` no `investimentos369` apontando para `storage/collector369/output/{provider}/`) e certificou em produção que a leitura funciona quando o arquivo está lá. Mas o Collector369 roda localmente, na máquina do Renan — nada move o arquivo do disco local até o servidor. O achado operacional da Sprint 13 foi que um upload manual (via File Manager da Hostinger) é frágil: um redeploy do `investimentos369` pode apagar `storage/collector369/output/` sem avisar, porque o Git só versiona o `.gitkeep` daquele diretório.

Esta sprint entrega o transporte automático desse arquivo, substituindo o upload manual.

## 2. Descoberta que precedeu o desenho

Antes de desenhar qualquer coisa, foi confirmado contra o ambiente real da Hostinger (não só documentação):

- Acesso SSH é inviável (usuário `u196460065`, shell `/sbin/nologin`).
- Existe uma conta FTP separada (`u196460065.collector369`), **restrita à raiz `storage/collector369/output`** — não enxerga o resto do `public_html`.
- FTPS explícito (`ftp_ssl_connect`) autentica, envia, verifica tamanho, baixa de volta e apaga com sucesso (`bin/ftp-connection-test.php`, `bin/ftp-upload-test.php`).

Essa descoberta definiu o desenho: não há necessidade de SSH/SFTP, e o protocolo (FTPS explícito, nunca FTP puro) já está validado.

## 3. Comando oficial

```
php bin/collector369 transport:production [--provider=<nome>]
```

Estende o dispatcher existente do `CollectorConsole` (que antes só reconhecia `collect:<provider>`) para também reconhecer `transport:production`, delegando para `Collector369\Transport\ProductionTransport`. Um único binário para todo o ciclo operacional — coleta e transporte continuam sendo **comandos separados e conscientes**, nunca encadeados automaticamente:

```
php bin/collector369 collect:investing
php bin/collector369 transport:production
```

Essa separação é deliberada: o Collector369 não decide sozinho quando um resultado de coleta está "pronto para produção" (ver limite institucional do Documento Mestre) — quem decide é o operador rodando o segundo comando.

## 4. Seleção de arquivos

Apenas **o arquivo mais recente de cada provider** em `OUTPUT_PATH/{provider}/` é transportado — não o histórico completo. O critério de "mais recente" é a ordenação lexicográfica do nome do arquivo (`{provider}_{Y-m-d_His}.{ext}`, convenção já usada por `CollectorStorage`), que coincide com a ordem cronológica. Decisão aprovada explicitamente por Renan: produção só precisa do que o Laboratório 369 efetivamente lê (o mais recente); o histórico completo permanece no disco local.

Os providers processados são descobertos dinamicamente (subdiretórios de `OUTPUT_PATH`), não há lista hardcoded — um terceiro provider futuro funciona sem qualquer alteração no código de transporte.

## 5. Upload seguro: arquivo temporário + rename

1. `ftp_put` para `{provider}/.tmp_{filename}` (nunca direto no nome final).
2. Verifica tamanho remoto do temporário (`ftp_size`) contra o tamanho local.
3. Baixa o temporário de volta e compara hash SHA-256 contra o arquivo local.
4. Só então `ftp_rename` do temporário para o nome final — o Laboratório 369 nunca enxerga um arquivo parcial, porque o nome final só passa a existir depois de 100% verificado.
5. Qualquer falha nesse caminho apaga o temporário remoto (best-effort) antes de reportar erro — nunca deixa lixo com o nome final.

## 6. Arquivo já existente com o mesmo nome — sem sobrescrita silenciosa

Antes de subir, o tamanho remoto do nome final é consultado. Se já existe:

- **Tamanho igual** → o conteúdo é baixado e comparado byte a byte (hash SHA-256) contra o local. Só é tratado como "já transportado" (`already_current`, sem novo upload) se o conteúdo for **idêntico**.
- **Tamanho diferente, ou tamanho igual com conteúdo diferente** → é tratado como **conflito** (`conflict`). O upload é abortado e nada é sobrescrito. Esse cenário exige investigação humana — não deveria acontecer em operação normal, já que o nome final embute o timestamp da coleta, mas o transporte nunca assume isso silenciosamente.

## 7. `.tmp` órfão de tentativa anterior

Antes de cada upload, o transporte verifica se já existe `{provider}/.tmp_{filename}` remotamente (resquício de uma execução anterior interrompida no meio do envio) e o remove antes de prosseguir. A remoção é feita com segurança: se o `.tmp` não existir, nada acontece (sem erro); se existir, a remoção é logada como recuperação de execução anterior.

## 8. Validação de tamanho/integridade

Dupla checagem: tamanho (`ftp_size`, filtro rápido) e hash SHA-256 do conteúdo completo, comparando o arquivo local com o que foi efetivamente baixado de volta do servidor após o upload. Custo de rede é desprezível (planilhas de dezenas de KB), e essa é a mesma técnica já validada em `bin/ftp-upload-test.php` contra o servidor real.

## 9. Falha, retry e códigos de saída

- **Falha transiente de conexão** (connect/login): até 3 tentativas com espera de 2s e depois 5s entre elas. Só a fase de conexão é retentada — falhas lógicas (conflito, integridade divergente pós-upload) **não são retentadas automaticamente**, porque retentar não resolve um conflito de conteúdo nem uma corrupção detectada, e retentar login cegamente após falha de credencial pode acionar bloqueios da conta na Hostinger.
- Cada provider é processado independentemente; falha em um não aborta os outros.
- Códigos de saída do comando: `0` = tudo transportado ou já atualizado; `1` = falha parcial (algum provider com erro/conflito, outros ok, ou todos com erro/conflito mas a conexão foi estabelecida); `2` = falha total (não foi possível conectar/autenticar, nenhum provider foi processado, ou faltam credenciais no `.env`).

## 10. Logs sem exposição de credenciais

Reaproveita a classe `Logger` já existente (`storage/logs/`). A senha do FTP nunca é logada — nem em sucesso, nem em falha. As mensagens registradas contêm apenas provider, nome de arquivo, caminho remoto, bytes e resultado.

## 11. Sem alteração nas classes protegidas

`ProductionTransport` lê exclusivamente do filesystem (`OUTPUT_PATH/{provider}/`) e não referencia nenhuma das 9 classes/interfaces protegidas do núcleo de coleta: `CollectorProviderInterface`, `ProviderRegistry`, `ProviderResolver`, `WorkflowRunner`, `CollectorManager`, `BrowserManager`, `DownloadManager`, `FileValidator`, `CollectorStorage`. A única alteração fora do namespace novo (`Collector369\Transport\`) foi em `CollectorConsole` (camada de composição/CLI, não núcleo), para reconhecer o novo comando.

## 12. Comportamento após um redeploy da Hostinger apagar os outputs

**Não há recuperação automática.** Se um redeploy do `investimentos369` apagar `storage/collector369/output/` em produção, o Collector369 não percebe isso sozinho — não há nenhum gatilho, cron ou monitoramento acionando o transporte. A restauração só acontece **quando `transport:production` for executado manualmente de novo**. Quando isso acontecer, o comando funciona normalmente: recria o subdiretório do provider (`ftp_mkdir`, tolerando "já existe") e reenvia o arquivo mais recente local, porque não há mais nome remoto colidindo — não é um modo especial, é o mesmo caminho de código de um envio novo.

## 13. O que esta sprint não fez (propositalmente)

- Nenhum agendamento automático (cron) do transporte após um redeploy ou após uma coleta.
- Nenhuma retenção/poda de arquivos remotos antigos (o transporte nunca escreveu histórico remoto, então não há o que podar).
- Nenhuma alteração em `bin/ftp-connection-test.php` ou `bin/ftp-upload-test.php` — mantidos como scripts de diagnóstico manual durante esta sprint, por decisão explícita de Renan.

## 14. Validação real (não só testes)

Além da suíte PHPUnit (com um `FtpClientInterface` fake, sem rede real), o comando foi executado contra o FTP real de produção (`ftp.investimentos369.com`, conta `u196460065.collector369`) com o único arquivo disponível localmente no momento (`investing_2026-07-23_033906.xlsx`). Resultado: `already_current` — o servidor já tinha exatamente esse conteúdo (upload manual da certificação da Sprint 13), confirmado por comparação byte a byte real, sem novo upload. Isso validou, contra o servidor real, o caminho completo de conexão FTPS, autenticação, consulta de tamanho remoto, download de verificação e comparação de hash — o caminho de upload+rename (`transported`) e o de conflito (`conflict`) foram validados pelos testes automatizados com o fake, já que não havia, no momento da validação, um arquivo remoto divergente disponível para reproduzir esses cenários com segurança contra produção.

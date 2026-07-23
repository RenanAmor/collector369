# Sprint 15 — Diagnóstico de Cobertura da Lista Oficial de Ativos Monitorados (Twelve Data)

## 1. Objetivo

Auditar ao vivo, com a chave `TWELVE_DATA_API_KEY` vigente (rotacionada antes desta sprint), a cobertura real dos 28 ativos da Lista Oficial de Ativos Monitorados pelo `TwelveDataProvider`, e implementar qualquer correção comprovada que caiba na arquitetura atual e no plano gratuito disponível. Estado inicial: 18/28 cobertos (Ações/ADRs 2/2, ETFs 7/7, Câmbio 7/7, Commodities parcial, Índices 0/11) — ver histórico de decisão em `Arquitetura-Multiprovider.md`.

## 2. Método

Todas as respostas abaixo vêm de chamadas reais à API (`https://api.twelvedata.com`), feitas com a chave do `.env` local (nunca exibida/logada), respeitando o rate limit gratuito (8 créditos/minuto — mesmo padrão de lote que o `TwelveDataProvider` já usa em produção). Três endpoints foram usados:

- `/quote` — o mesmo endpoint que o `TwelveDataProvider` usa em produção; é a fonte de verdade sobre cobertura.
- `/indices` — catálogo de referência (1272 entradas) para descobrir o símbolo correto de cada índice antes de gastar créditos de `/quote` adivinhando.
- `/forex_pairs` e `/symbol_search` — usados para procurar exaustivamente um símbolo de Cobre válido.

Nenhum símbolo foi inventado: todo símbolo citado abaixo foi confirmado (ou refutado) por uma resposta real da API, reproduzida neste documento.

## 3. Cobertura por ativo

### Commodities (4)

| Ativo | Símbolo testado | Resposta real da API | Status |
|---|---|---|---|
| Ouro Futuros | `XAU/USD` | 200 — `Gold Spot / US Dollar`, close 4048.68 | ✅ Coberto (sem mudança) |
| Soja Chicago Futuros | `SOYB` | 200 — `Teucrium Soybean Fund` (ETF) | ⚠️ Coberto via proxy ETF (limitação pré-existente, não é o futuro real de soja) |
| Cobre Futuros | `XCU/USD`, `COPPER`, `HG`, busca textual `copper`, busca por prefixo `XCU*` | `XCU/USD` → 404 `symbol not found`; `COPPER` → 404 `symbol not found`; `HG` → 200, mas resolve para *Hamilton Insurance Group* (ação não relacionada); busca textual/prefixo → nenhum instrumento de commodity cobre em nenhuma bolsa | ❌ Não suportado — nenhum símbolo de cobre existe no catálogo da Twelve Data, sob nenhum plano |
| Petróleo WTI Futuros | `WTI/USD` | 403 — `**symbol** WTI/USD is not available with your plan. You may select the appropriate plan at https://twelvedata.com/pricing` | ❌ Bloqueado pelo plano — símbolo existe e é reconhecido, requer upgrade |

### Índices (11) — 0/11 cobertos

| Ativo | Símbolo candidato (catálogo `/indices`) | Resposta real de `/quote` | Status |
|---|---|---|---|
| Oslo All Share | `OSEAX` (Oslo Bors All-Share Index_GI, exchange OSE) | 404 `The index unavailable` | Existe no catálogo, bloqueado pelo plano |
| BSE Sensex 30 | `BSESN` (S&P BSE SENSEX, exchange BSE) | 404 `The index unavailable` | Existe no catálogo, bloqueado pelo plano |
| Hang Seng Futuros | `HSI` (HANG SENG INDEX, exchange HKEX) | 404 `The index unavailable` | Existe no catálogo, bloqueado pelo plano |
| China A50 Futuros | `XIN9` (FTSE China A50 Index, exchange SSE) | 404 `The index unavailable` | Existe no catálogo, bloqueado pelo plano |
| SZSE Component | `399001` (SZSE Component Index, exchange SZSE) | 404 `The index unavailable` | Existe no catálogo, bloqueado pelo plano |
| Shanghai Composite | `000001` (SSE Composite Index, exchange SSE/mic `XSHG`) | Sem qualificador, `000001` resolve para outro ativo (ação *Ping An Bank*, SZSE); com `exchange=SSE` ou `mic_code=XSHG`, 404 `**symbol** or **figi** parameter is missing or invalid` | Existe no catálogo, mas o símbolo numérico é ambíguo; não foi possível isolar a chamada por erro de parâmetro — dado o padrão idêntico nos outros 5 índices confirmados, o bloqueio por plano é a explicação mais provável, mas fica registrado como não 100% confirmado isoladamente |
| Dow Jones Futuros | — | Nenhuma entrada em `/indices` contendo "Dow Jones" | Não existe no catálogo da Twelve Data |
| The Global Dow USD | — | Nenhuma entrada em `/indices` contendo "Global Dow" | Não existe no catálogo da Twelve Data |
| Dow Jones Shanghai | — | Nenhuma entrada em `/indices` contendo "Dow Jones" | Não existe no catálogo da Twelve Data |
| Índice Dólar Futuros | `DXY` | 404 `symbol not found: DXY` | Não existe no catálogo da Twelve Data |
| S&P 500 VIX Futuros | `VIX` | 404 `symbol not found: VIX` | Não existe no catálogo da Twelve Data |

### Ações/ADRs (2/2) — confirmados ao vivo com a nova chave

`VALE` (Vale S.A., NYSE), `PBR` (Petrobras S.A. ADR, NYSE) — ambos 200 OK.

### ETFs (7/7) — confirmados ao vivo com a nova chave

`EWZ`, `XLF`, `XLP`, `XLE`, `XME`, `EEM`, `SOXX` — todos 200 OK.

### Câmbio (7/7) — confirmados ao vivo com a nova chave

`USD/MXN`, `USD/NOK`, `USD/NZD`, `USD/AUD`, `USD/KRW`, `USD/CNY`, `EUR/BRL` — todos 200 OK.

## 4. Causa objetiva das lacunas

- **Índices (0/11):** bloqueio estrutural do plano gratuito da Twelve Data. Dos 11 ativos, 6 existem no catálogo de referência (`/indices`) mas o endpoint `/quote` recusa todos com `"The index unavailable"` — não é um problema de símbolo errado, é o plano gratuito negando cotação de índices como categoria. Os outros 5 (Dow Jones Futuros, The Global Dow, Dow Jones Shanghai, Índice Dólar, VIX) não existem em nenhum catálogo da Twelve Data, sob nenhum nome testado — não é uma questão de plano, a Twelve Data simplesmente não oferece esses instrumentos.
- **Cobre:** ausência de instrumento. Diferente do WTI (que existe e é só bloqueado por plano), a Twelve Data não tem *nenhum* símbolo de cobre como commodity, em nenhuma variação testada (código ISO `XCU/USD`, nome `COPPER`, ticker de futuro COMEX `HG`, busca textual). Não é uma lacuna de plano — é uma lacuna de catálogo.
- **WTI:** bloqueio de plano. Símbolo `WTI/USD` existe e é reconhecido pela API; a negativa (`403`) é explícita sobre o motivo — plano gratuito insuficiente.

## 5. Correções implementadas nesta sprint

**Nenhuma.** Todo candidato de símbolo testado para Índices, Cobre e WTI falhou por causa comprovada e externa ao código (símbolo inexistente no catálogo da Twelve Data, ou bloqueio explícito de plano). Não há nenhuma mudança de símbolo, de configuração ou de código que aumente a cobertura dentro da arquitetura atual e do plano gratuito vigente — adicionar qualquer um desses símbolos ao `TwelveDataProvider` produziria apenas erros em produção. Por isso `CollectorConsole::TWELVE_DATA_SYMBOLS` permanece com os mesmos 18 símbolos da Sprint 9, e nenhuma das 9 classes protegidas do núcleo foi tocada (diff zero confirmado).

## 6. Cobertura final

18/28 (64%) — inalterada em relação à Sprint 9, agora reconfirmada ao vivo com a chave de API rotacionada. A rotação de chave não teve efeito sobre o plano/tier: os mesmos bloqueios já documentados na Sprint 8 persistem de forma idêntica.

## 7. Bloqueios reais remanescentes (decisão de produto, não de engenharia)

1. **Upgrade de plano pago da Twelve Data** — resolveria WTI e, com alta probabilidade, os 6 índices hoje bloqueados por `"index unavailable"` (Oslo All Share, BSE Sensex 30, Hang Seng, China A50, SZSE Component, e provavelmente Shanghai Composite).
2. **Segundo Provider dedicado** — necessário para Cobre e para os 5 índices que não existem em nenhum catálogo da Twelve Data (Dow Jones Futuros, Global Dow, Dow Jones Shanghai, Índice Dólar, VIX), já que nenhum plano da Twelve Data os oferece.

Nenhuma das duas opções foi decidida nesta sprint — ambas exigem decisão do Product Owner (orçamento/prioridade), não implementação.

## 8. Execução ponta a ponta desta sprint

- Suíte de testes PHPUnit: 43/43 passando.
- Coleta real via `php bin/collector369 collect:twelvedata`: sucesso, 18 ativos coletados e entregues em `storage/collector369/output/twelvedata/` (dentro da árvore do `investimentos369`, caminho canônico da Sprint 13).
- Transporte via `php bin/collector369 transport:production`: `investing` já estava atualizado; `twelvedata` transportado e verificado com sucesso (7402 bytes) no FTP de produção.
- Validação de leitura no Laboratório 369 (`investimentos369`, fora do repositório do Collector369): smoke test direto via `app/autoload.php` (bootstrap real, não o do PHPUnit — lição da Sprint 12) confirmou leitura correta de ambos os providers (`investing`: 3 linhas; `twelvedata`: 19 linhas, cabeçalho e primeira linha de dados conferidos).
- Diff zero confirmado nas 9 classes protegidas do núcleo (`CollectorProviderInterface`, `ProviderRegistry`, `ProviderResolver`, `WorkflowRunner`, `CollectorManager`, `BrowserManager`, `DownloadManager`, `FileValidator`, `CollectorStorage`) — na verdade, diff zero em todo o repositório, já que nenhuma correção de símbolo foi possível.

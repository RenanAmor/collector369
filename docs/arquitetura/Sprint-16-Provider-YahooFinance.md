# Sprint 16 — Segundo Provider de Cotações (Yahoo Finance) e Cobertura dos 13 Ativos Bloqueados

## 1. Objetivo

Selecionar e implementar a fonte complementar mínima capaz de cobrir o maior número possível dos 13 ativos da Lista Oficial de Ativos Monitorados sem cobertura pela Twelve Data (Cobre Futuros, Petróleo WTI Futuros e os 11 Índices — ver `Sprint-15-Diagnostico-Cobertura-TwelveData.md`), priorizando dados reais e sem apresentar proxy como ativo real sem disclosure.

## 2. Fontes auditadas

| Fonte | Tipo (Política item 2) | Resultado |
|---|---|---|
| Twelve Data (upgrade de plano) | API oficial paga | Descartado nesta sprint — mesmo pago, catálogo não tem Cobre nem 5 dos 11 índices (ver Sprint 15); decisão de contratar upgrade também é do PO, não de engenharia. |
| Alpha Vantage | API oficial gratuita (chave própria obtida via cadastro público, `S007Z7F2LDZJCB1G`) | Testado ao vivo: `function=WTI` retorna série diária real (EIA, preço à vista, ex. USD 84.38/barril em 2026-07-20); `function=COPPER` retorna série real mas **mensal** (Global Price of Copper, FRED/IMF, ex. USD 13552/tonelada em 06/2026). Cobre apenas 2 dos 13 ativos, nenhum índice (a API não oferece cotação de índice bruto como produto). Rate limit gratuito: 25 requisições/dia. |
| Yahoo Finance (`query1.finance.yahoo.com/v8/finance/chart`) | Automação de interface — endpoint HTTP não documentado oficialmente pela Yahoo (sem contrato, sem chave, sem versionamento publicado) | Testado ao vivo: cobre os 13/13 ativos com dado real e atual (ver seção 4). Sem CAPTCHA/crumb — não há proteção anti-automação ativa nesse endpoint específico; exige apenas cabeçalho `User-Agent`. O endpoint `v7/finance/quote` (que permitiria lote) **retorna `401 Unauthorized` exigindo crumb/cookie de sessão** — sinal deliberado de restrição de acesso; **não foi usado**, por decisão de não contornar esse controle (Política item 1). Usado apenas `v8/finance/chart`, sem nenhuma tentativa de obter crumb/cookie. |

**Decisão:** Yahoo Finance (`v8/finance/chart`) foi adotado como a fonte complementar, por ser a única capaz de cobrir a totalidade dos 13 ativos bloqueados com dado real. Alpha Vantage foi homologado tecnicamente (chave real obtida, dados reais confirmados), mas não adotado nesta sprint por cobrir apenas 2/13 ativos — poderá ser retomado no futuro caso o WTI/Cobre precisem de uma fonte de nível 1 mais formal que o Yahoo.

## 3. Classificação institucional (Política Oficial de Aquisição de Dados L369)

Conforme item 7 da Política, todo novo Provider deve declarar seu tipo de fonte:

- **Tipo:** Automação de interface (item 2.3 da Política) — não é API oficial (não documentada, sem contrato/versionamento publicado pela Yahoo).
- **Por que não uma fonte de nível 1/2:** nenhuma API oficial gratuita ou paga com cobertura equivalente foi encontrada para índices globais (Alpha Vantage não oferece o produto; Twelve Data bloqueia por plano ou não tem no catálogo — ver Sprint 15).
- **Sem contorno de proteção:** o endpoint usado (`v8/finance/chart`) não exige crumb/cookie/CAPTCHA. O endpoint que exige (`v7/finance/quote`) foi deliberadamente evitado.
- **Isolamento:** toda a lógica de acesso está contida em `YahooFinanceProvider`, sem se misturar a `WorkflowRunner`/`CollectorManager`/demais Providers.
- **Plano de contingência:** se a Yahoo alterar ou bloquear o endpoint, `YahooFinanceProvider::collect()` lança `CollectorException`, capturada por `WorkflowRunner` (mesmo tratamento de falha já usado pelos outros dois Providers) — a coleta falha de forma controlada, sem quebrar o restante do pipeline nem os outros Providers.
- **Rastreabilidade:** cada cotação é atribuída ao símbolo Yahoo Finance consultado e ao campo `regularMarketTime` retornado pela própria API — nenhum valor é inventado ou interpolado.

## 4. Comprovação ao vivo — os 13 ativos (23/07/2026)

Todas as respostas abaixo vieram de chamadas reais a `https://query1.finance.yahoo.com/v8/finance/chart/{símbolo}?interval=1d&range=1d`, com cabeçalho `User-Agent` de navegador, sem nenhum símbolo inventado.

### Correspondência direta (11) — mesmo instrumento nomeado na Lista Oficial

| Ativo (Lista Oficial) | Símbolo Yahoo | Nome retornado pela API | Preço | Timestamp real |
|---|---|---|---|---|
| Petróleo WTI Futuros | `CL=F` | Crude Oil Sep 26 | 92.36 | 2026-07-23 |
| Cobre Futuros | `HG=F` | Copper Sep 26 | 6.3375 | 2026-07-23 |
| Dow Jones Futuros | `YM=F` | Mini Dow Jones Indus.-$5 Sep 26 | 51919.0 | 2026-07-23 |
| The Global Dow USD | `^GDOW` | The Global Dow (USD) | 6845.02 | 2026-07-23 |
| BSE Sensex 30 | `^BSESN` | S&P BSE SENSEX | 76391.39 | 2026-07-23 |
| Dow Jones Shanghai | `^DJSH` | Dow Jones Shanghai Index | 544.53 | 2026-07-23 |
| SZSE Component | `399001.SZ` | Shenzhen Index | 14123.307 | 2026-07-23 |
| Shanghai Composite | `000001.SS` | SSE Composite Index | 3876.777 | 2026-07-23 |
| Índice Dólar Futuros | `DX-Y.NYB` | ICE US Dollar Index - Index - C | 101.438 | 2026-07-23 |
| Oslo All Share | `OSEAX.OL` | Oslo Børs All-share Index_GI | 2390.3 | 2026-07-23 |
| S&P 500 VIX Futuros | `^VIX` | CBOE Volatility Index | 18.70 | 2026-07-23 |

Nota: o símbolo legado `^OSEAX` também existe no catálogo da Yahoo, mas retorna dado congelado desde novembro de 2020 (`regularMarketTime` parado) — **descartado**; `OSEAX.OL` é o símbolo com dado corrente e foi o adotado.

**Correção do PO (validação visual em produção, 23/07/2026):** o Checklist 369 exige o índice VIX **à vista** (spot), não o contrato futuro. `^VIX` (CBOE Volatility Index) é, portanto, exatamente o dado pedido — não um proxy. A linha do VIX foi movida da tabela de proxy (abaixo) para a de correspondência direta.

### Correspondência via proxy — índice à vista, não o contrato futuro (2)

A Lista Oficial nomeia estes dois ativos como "Futuros", mas a Yahoo Finance não oferece gratuitamente uma série contínua do contrato futuro para eles (testado e confirmado ausente: `CN=F`, `HSI=F` — ambos `404 Not Found`). Os símbolos abaixo são o índice à vista real do mesmo mercado, **não o futuro** — mesma natureza de disclosure já usada para Soja/`SOYB` na Sprint 9/15.

| Ativo (Lista Oficial) | Símbolo Yahoo usado | Nome retornado | Preço | Ressalva |
|---|---|---|---|---|
| Hang Seng Futuros | `^HSI` | HANG SENG INDEX | 25210.81 | Índice à vista (HKEX), não o futuro |
| China A50 Futuros | `XIN9.FGI` | FTSE China A50 Index | 15287.21 | Índice à vista, não o futuro (SGX) |

## 5. Cobertura resultante da Lista Oficial (31 ativos)

| Categoria | Antes (Sprint 15) | Depois (Sprint 16, com correção do VIX) |
|---|---|---|
| ✅ Cobertura real | 17/31 | **28/31** (+11, via Yahoo Finance, incluindo VIX à vista) |
| 🟡 Cobertura via proxy (disclosed) | 1/31 (Soja/SOYB) | **3/31** (+2: Hang Seng, China A50 — via Yahoo) |
| ❌ Sem cobertura | 13/31 | **0/31** |

**Todos os 31 ativos da Lista Oficial agora têm alguma cotação real fluindo pelo pipeline** — 28 como o instrumento exato nomeado (incluindo VIX à vista, o dado exigido pelo Checklist 369), 3 como proxy explicitamente documentado (Soja via ETF, Hang Seng e China A50 via índice à vista). Nenhum dos 3 proxies remanescentes é apresentado como se fosse o ativo real sem esta ressalva. Ver `Sprint-16-Provider-SinaFinance.md` para a contagem final consolidada de 32 ativos (após adição do Minério de Ferro).

## 6. O que esta sprint não decidiu

- Se os 3 proxies remanescentes (Soja/SOYB, Hang Seng/`^HSI`, China A50/`XIN9.FGI`) são aceitáveis como substitutos definitivos dos ativos futuros nomeados na Lista Oficial, ou se o PO prefere uma fonte paga com o contrato futuro real para algum deles — decisão de produto, não de engenharia.
- Se a classificação "automação de interface" da Yahoo Finance deve ser revista no futuro (ex.: se a Yahoo formalizar/documentar o endpoint, ou se ele for bloqueado) — a Política pede reavaliação periódica (item 2).

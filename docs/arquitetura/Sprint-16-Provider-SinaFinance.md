# Sprint 16 — Provider Sina Finance (Minério de Ferro)

## 1. Decisão do PO

Durante a execução da Sprint 16, Renan (PO) determinou que o **Minério de Ferro** deve ser adicionado à Lista Oficial de Ativos Monitorados, coletado especificamente da **Sina Finance** (`https://finance.sina.com.cn/futures/quotes/I0.shtml`, identificador de página `I0` — contrato contínuo de Minério de Ferro), com os requisitos explícitos:

- último preço, variação percentual diária, data/hora da cotação e identificação da fonte, no mínimo;
- **sem substituição por ETF ou proxy**;
- integração pelo endpoint de dados real usado pela própria página, não por raspagem da renderização visual.

## 2. Endpoint identificado

A página `finance.sina.com.cn/futures/quotes/I0.shtml` não carrega a cotação no HTML — o preço é preenchido via JavaScript a partir do feed público de tempo real da Sina:

```
https://hq.sinajs.cn/list=nf_I0
```

Testado ao vivo (23/07/2026), com cabeçalhos `User-Agent` e `Referer: https://finance.sina.com.cn/`:

```
var hq_str_nf_I0="铁矿石连续,230000,747.500,749.500,742.500,0.000,743.500,744.000,743.500,0.000,747.000,9,308,487626.000,57834,连,铁矿石,2026-07-23,1,...";
```

- Resposta em texto plano, codificação **GBK** (não UTF-8) — decodificada via `mb_convert_encoding($resposta, 'UTF-8', 'GBK')`.
- Sem o cabeçalho `Referer` correspondente ao domínio de origem, o endpoint retorna `403 Forbidden`. Este é o mesmo valor estático que a própria página envia ao carregar seu widget de cotação (confirmado no código-fonte de referência citado abaixo) — não é um desafio de sessão, cookie ou CAPTCHA, portanto seu envio não configura contorno de proteção anti-automação (Política item 1). O símbolo alternativo sem o prefixo `nf_` (`list=I0`) também responde, mas com dado **congelado desde 2024-07-17** — descartado.

## 3. Mapeamento de campos (verificado, não inventado)

O formato posicional do feed não é documentado oficialmente pela Sina. O mapeamento abaixo foi conferido contra a implementação de referência do projeto open source **AKShare** (`akshare/futures/futures_zh_sina.py`, função `futures_zh_spot`, mercado `"CF"` — commodities), amplamente usado pela comunidade de dados financeiros chinesa para este mesmo feed:

| Índice | Campo | Valor observado |
|---|---|---|
| 0 | nome do contrato | `铁矿石连续` (Minério de Ferro Contínuo) |
| 1 | hora (HHMMSS) | `230000` → 23:00:00 |
| 2–4 | abertura / máxima / mínima | 747.500 / 749.500 / 742.500 |
| 5 | fechamento anterior | `0.000` (não populado para o contrato contínuo/sessão noturna) |
| 6–7 | preço de compra / venda | 743.500 / 744.000 |
| 8 | **preço atual** | **743.500** |
| 9 | preço médio | `0.000` |
| 10 | **preço de liquidação anterior (前结算价)** | **747.000** |
| 11–14 | volume compra/venda, posições em aberto, volume | 9 / 308 / 487626 / 57834 |
| 17 | data (YYYY-MM-DD) | `2026-07-23` (índice confirmado por inspeção direta da resposta real; o AKShare descarta essa posição como coluna extra não utilizada, mas ela é estável e sempre presente) |

**Variação e variação percentual** são calculadas pelo Provider a partir de `preço atual − preço de liquidação anterior`, seguindo a convenção do mercado futuro chinês (variação diária de futuros na China é reportada contra o **preço de liquidação anterior**, não o fechamento anterior — daí o campo 5 zerado/irrelevante para este contrato). Nenhum valor de variação vem pronto da API; é aritmética direta sobre dois valores reais retornados, sem interpretação de negócio.

## 4. Classificação institucional

- **Tipo (Política item 2):** Automação de interface — endpoint HTTP não documentado oficialmente pela Sina, sem chave/contrato/versionamento.
- **Por que não uma fonte de nível 1/2:** não há API oficial gratuita para futuros da Dalian Commodity Exchange; a exigência explícita do PO era usar exatamente esta página/fonte.
- **Sem contorno de proteção:** o único controle de acesso observado é o `Referer` estático, satisfeito com o mesmo valor que a página legítima envia — não há crumb, cookie de sessão ou CAPTCHA.
- **Rastreabilidade:** cada linha da planilha inclui uma coluna `Fonte` (`Sina Finance (hq.sinajs.cn)`), além do símbolo (`I0`) e do timestamp retornado pela própria API.
- **Plano de contingência:** falha de parsing ou resposta inesperada lança `CollectorException`, tratada por `WorkflowRunner` como qualquer outro Provider — falha isolada, não derruba o pipeline.

## 5. Resultado

Minério de Ferro passa a ser coletado como ativo real (não proxy), via `SinaFinanceProvider`, símbolo `I0`. A Lista Oficial de Ativos Monitorados passa de 31 para **32 ativos** (adição aprovada pelo PO nesta sprint).

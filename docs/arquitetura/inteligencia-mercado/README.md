# Phi — Inteligência de Mercado do Collector369

## Status

**Nome oficial:** Phi

Módulo independente em desenvolvimento dentro do repositório Collector369.

Nesta etapa, a Inteligência de Mercado não está integrada ao Motor Macro. A integração será considerada somente depois da documentação, implementação, testes e validação histórica.

## Objetivo

Desenvolver uma inteligência capaz de analisar a estrutura fractal do mercado, afunilando a leitura dos períodos maiores para os menores até identificar possíveis zonas de entrada.

A análise combinará:

- estrutura de mercado;
- Ondas de Elliott;
- Método Wyckoff;
- relações de Fibonacci;
- análise multitemporal;
- confluência de evidências;
- gestão de risco;
- validação por backtest.

## Princípio fractal

Nenhuma onda, fase, evento ou entrada poderá existir sem referência a:

- ativo;
- período gráfico;
- intervalo temporal;
- contexto do período superior;
- estrutura do período analisado;
- confirmação no período inferior, quando aplicável.

A análise seguirá o fluxo:

1. contexto macro;
2. tendência dominante;
3. estrutura intermediária;
4. onda ou fase atual;
5. setup;
6. gatilho;
7. entrada;
8. stop;
9. alvos;
10. invalidação.

## Responsabilidades dos módulos

### Timeframe

Representa períodos gráficos e organiza as relações hierárquicas entre eles.

### MarketStructure

Identifica tendências, topos, fundos, impulsos, correções, rompimentos, faixas e mudanças estruturais.

### ElliottWave

Mantém contagens principais e alternativas, graus de onda, regras obrigatórias e níveis de invalidação.

### Wyckoff

Identifica acumulação, distribuição, fases, eventos e relações entre preço, amplitude e volume.

### Fibonacci

Calcula retrações, extensões, projeções e regiões de confluência.

### Confluence

Combina as evidências produzidas pelos diferentes módulos sem apagar divergências entre elas.

### Entry

Transforma uma estrutura confirmada em possível zona de entrada, sempre acompanhada do gatilho e da justificativa.

### Risk

Calcula stop técnico, invalidação, alvos e relação entre risco e retorno.

### Backtest

Valida as regras utilizando somente informações que estavam disponíveis no momento histórico analisado.

## Regra de saída

O sistema não produzirá apenas comandos de compra ou venda.

Toda possível entrada deverá apresentar:

- cenário principal;
- cenário alternativo;
- período da entrada;
- contexto superior;
- evidências;
- zona de entrada;
- gatilho;
- stop técnico;
- alvos;
- nível de invalidação;
- relação risco-retorno;
- grau de confiança;
- horário da análise.

Quando as condições forem insuficientes, o resultado deverá informar:

> Nenhuma entrada confirmada neste momento.

## Integração futura

Quando validada, a Inteligência de Mercado poderá fornecer ao Motor Macro:

- tendência por período;
- estrutura fractal;
- fase provável de Elliott;
- fase provável de Wyckoff;
- zonas relevantes;
- cenários;
- possíveis entradas;
- invalidações;
- métricas de confiança.

O Motor Macro continuará responsável pela visão ampla do mercado. O Collector369 fornecerá a inteligência estrutural e multitemporal dos preços.


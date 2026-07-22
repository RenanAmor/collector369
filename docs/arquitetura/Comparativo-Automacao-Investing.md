# Documento de Apoio à Decisão Arquitetural

## Automação de Coleta — Investing.com (pós Sprint de Diagnóstico)

---

## Contexto

A Sprint de Diagnóstico concluiu que a arquitetura atual do Collector369 (PHP orquestrando um subprocesso Node/Playwright com Chrome real e perfil persistente) está tecnicamente correta e funcional em toda a infraestrutura sob seu controle, mas é bloqueada pelo Cloudflare Bot Management antes de acessar a Carteira do Investing.com. A causa raiz identificada é `navigator.webdriver = true`, sinal estrutural exposto pelo Chrome quando controlado via protocolo de automação (CDP) — inerente a qualquer ferramenta baseada em Playwright, Puppeteer ou Selenium, não removível por configuração.

Este documento compara, de forma objetiva e sem recomendar uma escolha final, as três alternativas arquiteturais levantadas durante o diagnóstico. Nenhuma quarta alternativa foi incluída. Nenhuma implementação foi realizada.

---

## Quadro-resumo

| Critério | 1. Extensão de Navegador | 2. Fluxo Semi-Manual | 3. Fonte de Dados Alternativa |
|---|---|---|---|
| Complexidade de implementação | Alta | Baixa | Média |
| Robustez frente ao bloqueio | Alta | Muito alta | Muito alta |
| Impacto na arquitetura atual | Médio-alto | Baixo | Baixo-médio |
| Reutilização futura | Alta | Média | Alta |
| Aderência aos princípios L369 | Alta | Alta | Alta |
| Custo de manutenção | Médio-alto | Baixo | Baixo (técnico) / recorrente (financeiro) |
| Escalabilidade | Baixa-média | Baixa | Alta |
| Elimina intervenção humana | Sim (parcial — navegador precisa estar ativo/logado) | Não | Sim |

---

## Alternativa 1 — Automação via Extensão de Navegador

**Descrição da solução:** Construir uma extensão de navegador própria do Collector369 (Manifest V3), instalada no navegador real do operador, que executa a navegação e o clique no botão de exportação a partir de dentro do navegador comum — reproduzindo o princípio que já funciona hoje no Automa, mas como código próprio, documentado e integrado ao pipeline do Collector369.

**Como funciona:** A extensão roda como content script/background service worker dentro do Chrome do usuário. Ela localiza e aciona o botão de exportação na página da Carteira, e comunica o resultado (arquivo baixado) para fora do navegador — por exemplo, via um arquivo de sinalização em disco que o Collector369 (PHP) monitora para dar sequência à validação/armazenamento.

**Vantagens:**
- Única abordagem comprovadamente capaz de evitar a detecção do Cloudflare (mesmo princípio do Automa, que já funciona).
- Roda dentro da sessão real do navegador do usuário, sem o sinal `navigator.webdriver`.
- Mantém o operador com visibilidade/controle do processo.

**Desvantagens:**
- Exige que o navegador do operador esteja aberto e logado no momento da coleta — não é um processo autônomo de servidor.
- Maior complexidade de desenvolvimento (Manifest V3, permissões, empacotamento, exposição a mudanças de política das lojas de extensão).
- Exige mecanismo extra de comunicação entre extensão e Collector369 (arquivo de sinalização, servidor local, etc.), aumentando a superfície de integração.

**Complexidade de implementação:** Alta — construção de uma nova camada de automação, em tecnologia diferente (JS de extensão) da já usada no Collector369.

**Robustez frente às limitações encontradas:** Alta — resolve diretamente a causa raiz identificada (`navigator.webdriver`), pois não usa protocolo de automação externo.

**Impacto na arquitetura do Collector369:** Médio-alto — introduz um componente fora do modelo atual ("PHP orquestra subprocesso Node"); BrowserManager/WorkflowRunner precisariam de um novo adaptador para esse tipo de coleta.

**Impacto na reutilização futura da tecnologia:** Alta — a extensão (ou seu framework base) pode ser reaproveitada para outros providers com o mesmo tipo de bloqueio (ex.: TradingView).

**Aderência aos princípios do Ecossistema L369:** Alta — o Collector369 continua apenas coletando/validando/entregando; a extensão só executa a coleta bruta.

**Riscos:** Manutenção contínua frente a mudanças no site e nas políticas de extensões dos navegadores; risco (menor, mas não nulo) de detecção futura da própria extensão; dependência de o navegador do operador estar sempre disponível/logado.

**Custos de manutenção:** Médio-alto — disciplina de código adicional (extensão), com ciclo de testes próprio.

**Escalabilidade:** Baixa a média — depende de uma sessão de navegador humana ativa; não escala facilmente para coletas paralelas/distribuídas.

**Recomendação técnica:** Viável e é a opção que mais diretamente resolve a causa raiz identificada; adequada se a prioridade for eliminar a dependência do Automa mantendo alta confiabilidade de acesso, aceitando maior investimento de desenvolvimento e a permanência de uma dependência humana/navegador ativo.

---

## Alternativa 2 — Fluxo Semi-Manual

**Descrição da solução:** O operador realiza manualmente a exportação da planilha no Investing.com (como já ocorre hoje), salvando o arquivo em uma pasta monitorada. O Collector369 assume a partir daí: detecta o novo arquivo, valida e armazena no diretório oficial de saída.

**Como funciona:** Um mecanismo simples de monitoramento de pasta detecta um arquivo novo depositado manualmente (ou pelo Automa) numa pasta de staging; a partir daí, o pipeline já construído e testado na Sprint 4 (`FileValidator` → `CollectorStorage`) processa exatamente como já está implementado.

**Vantagens:**
- Reaproveita integralmente o trabalho já construído e testado na Sprint 4 (`DownloadManager`/`FileValidator`/`CollectorStorage` continuam exatamente como estão).
- Elimina completamente o risco de bloqueio por bot-detection — não há automação de navegador na etapa de acesso ao site.
- Menor complexidade de implementação entre as três alternativas.

**Desvantagens:**
- Não elimina a intervenção manual — o objetivo original de substituir o Automa por automação completa fica parcialmente não atendido.
- Não resolve o problema institucional de "não depender de automação externa" caso a exportação continue sendo feita pelo Automa.

**Complexidade de implementação:** Baixa — um watcher de diretório mais reaproveitamento do pipeline já pronto.

**Robustez frente às limitações encontradas:** Muito alta — não há bloqueio possível, pois não há automação de acesso ao site nessa etapa.

**Impacto na arquitetura do Collector369:** Baixo — `BrowserManager`/`InvestingProvider` seriam substituídos por um provider de importação de pasta, mantendo o restante do pipeline intocado.

**Impacto na reutilização futura da tecnologia:** Média — o padrão "provider que lê de uma pasta" é reutilizável para outros fluxos semi-manuais, mas não avança a automação de navegador para outros providers com o mesmo problema.

**Aderência aos princípios do Ecossistema L369:** Alta — o Collector369 continua estritamente na função de validar/armazenar/entregar.

**Riscos:** Depende de disciplina humana para realizar a exportação manual com regularidade; risco de atraso ou esquecimento, já que não há mais automação cobrindo essa etapa.

**Custos de manutenção:** Baixo — solução simples, poucas partes móveis.

**Escalabilidade:** Baixa — permanece limitada pela disponibilidade humana para a etapa manual; não escala para múltiplas contas/carteiras sem múltiplas pessoas.

**Recomendação técnica:** Opção de menor risco técnico e menor esforço; adequada como solução de transição enquanto uma decisão de automação completa não é tomada, mas não resolve plenamente o objetivo original de eliminar a intervenção manual/Automa.

---

## Alternativa 3 — Fonte de Dados Alternativa (provedor autorizado)

**Descrição da solução:** Substituir o Investing.com como fonte de dados por um provedor financeiro com acesso de dados autorizado (API oficial, feed pago ou parceria), eliminando a necessidade de qualquer automação de navegador para esse provider.

**Como funciona:** O Collector369 passaria a ter um novo provider consumindo uma API HTTP diretamente (sem navegador), retornando dados já estruturados, que seguem para validação/armazenamento normalmente — mesmo padrão já previsto em `CollectorProviderInterface`.

**Vantagens:**
- Elimina completamente o problema de bot-detection — não há scraping/automação de navegador envolvida.
- Potencialmente mais estável, com contrato de serviço formal (SLA), em vez de depender do comportamento de uma página web sujeita a mudanças.
- Arquitetura mais simples, alinhada ao modelo "provider consome API" já previsto no desenho original.

**Desvantagens:**
- Não resolve o objetivo declarado de coletar especificamente os dados da Carteira existente no Investing.com — pode exigir remapear os ~30 ativos para os identificadores do novo provedor, e possivelmente alterar o formato consumido pelo Laboratório 369 (Checklist 369).
- Provedores de dados financeiros de qualidade costumam ser pagos, com custo recorrente ainda não orçado.
- Ainda não verificado se existe um provedor único que cubra o conjunto específico de ativos usados na "força do dia" (commodities, moedas emergentes, índices regionais, posição de estrangeiros na B3, etc.).

**Complexidade de implementação:** Média — implementar um novo provider consumindo API é tecnicamente mais simples que os outros dois, mas a etapa de pesquisa/contratação/mapeamento de dados pode ser significativa.

**Robustez frente às limitações encontradas:** Muito alta — elimina o problema na raiz, pois não há navegador nem bot-detection envolvidos.

**Impacto na arquitetura do Collector369:** Baixo a médio — encaixa-se no modelo de providers já existente, sem exigir `BrowserManager` para esse provider específico.

**Impacto na reutilização futura da tecnologia:** Alta — um provider baseado em API é o padrão mais simples de reaproveitar para futuras fontes com API própria (B3 e Banco Central são candidatos naturais).

**Aderência aos princípios do Ecossistema L369:** Alta — mantém o Collector369 exclusivamente como coletor, reduzindo o risco institucional de depender de scraping de terceiros.

**Riscos:** Custo financeiro recorrente não orçado; dependência de fornecedor externo (risco contratual/comercial); possível descontinuidade caso o provedor mude termos de serviço; pode não cobrir exatamente os mesmos ativos hoje usados, exigindo validação de equivalência com o Laboratório 369.

**Custos de manutenção:** Baixo tecnicamente, porém com custo financeiro recorrente (assinatura/licença) que as outras duas alternativas não têm.

**Escalabilidade:** Alta — APIs tendem a escalar bem para múltiplas coletas/frequências sem depender de navegador ou intervenção humana.

**Recomendação técnica:** Opção mais robusta a longo prazo do ponto de vista técnico e arquitetural, mas depende de uma etapa de pesquisa/negociação comercial ainda não realizada, e pode não ser um substituto direto e equivalente da Carteira específica hoje usada.

---

## Encerramento

Este documento é exclusivamente informativo, destinado a subsidiar a decisão arquitetural. Nenhuma das três alternativas foi implementada, nenhuma quarta alternativa foi proposta, e nenhum plano de implementação foi elaborado. A participação de Claude Code nesta Sprint de Arquitetura encerra-se com a entrega deste documento, até que a arquitetura oficial seja definida.

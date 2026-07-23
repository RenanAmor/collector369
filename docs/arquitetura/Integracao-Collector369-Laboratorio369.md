# Documento de Apoio à Decisão Arquitetural

## Integração entre Collector369 e Laboratório 369 (Sprint 10)

---

## Contexto

A Sprint 10 tem como objetivo único integrar o Collector369 ao Laboratório 369, de forma que o Laboratório consiga consumir automaticamente os dados já produzidos pelos Providers existentes (`investing`, `twelvedata`).

Auditoria prévia à escrita deste documento constatou dois fatos relevantes:

1. **O Laboratório 369 (`c:/Projetos/L369/laboratorio369`) ainda não possui nenhum código** — existe apenas o commit fundacional (`foundation: initialize Laboratorio369`), com diretórios vazios (`notebooks/`, `experiments/`, `prototypes/`, `datasets/`, `benchmarks/`, `docs/*`). Não há hoje nenhum mecanismo de recepção de dados de mercado no Laboratório — a pergunta "como ele recebe dados hoje" tem resposta vazia.
2. **O lado do Collector369 já foi desenhado prevendo esta entrega.** `CollectorStorage` grava cada coleta validada em `OUTPUT_PATH/{provider}/{provider}_{timestamp}.{ext}`, e tanto o docblock da classe quanto o `.env.example` já rotulam esse diretório como "saída oficial de entrega ao Laboratório 369" (escrito nas Sprints 4/7, antes desta sprint existir).

Este documento compara as alternativas de mecanismo de integração entre os dois projetos, à luz de três documentos de governança do Ecosystem já vigentes:

- **ADR-001 — Separação entre Pesquisa e Produção**: define quatro áreas (Ecosystem, Laboratório 369, L369 Platform, Projetos) e atribui explicitamente à **L369 Platform** a responsabilidade por "integrações" entre as demais áreas.
- **Arquitetura Oficial L369**: descreve o fluxo oficial `Laboratório → Arquitetura → L369 Platform → Projeto → Produção` — ou seja, o fluxo institucional documentado é o de conhecimento nascendo no Laboratório e descendo até os Projetos, não o inverso. A integração desta sprint (Projeto → Laboratório, dados fluindo "para cima") é, portanto, um fluxo de dados operacional, distinto do fluxo de evolução arquitetural descrito nesse documento.
- **Política Oficial de Aquisição de Dados L369**: trata de aquisição de dados de **fontes externas** ao ecossistema; não se aplica diretamente aqui (Collector369 e Laboratório 369 são ambos internos ao L369), mas seu espírito institucional — preferir o mecanismo mais simples e formal viável agora, documentar a decisão, permitir migração futura — é adotado por analogia.

Nenhuma implementação foi realizada até a aprovação deste documento.

---

## Quadro-resumo

| Critério | 1. Leitura direta de arquivos | 2. Integração mediada pela L369 Platform | 3. API HTTP no Collector369 | 4. Banco de dados compartilhado | 5. Fila/barramento de eventos |
|---|---|---|---|---|---|
| Complexidade de implementação | Baixa | Média-alta | Média | Média-alta | Alta |
| Infraestrutura nova necessária | Nenhuma | Nova dependência composer + módulo | Servidor HTTP long-running | Servidor de banco + schema | Broker de fila/eventos |
| Aderência à ADR-001 (integrações = Platform) | Baixa | Alta | Baixa | Baixa | Baixa |
| Impacto no Collector369 | Zero (usa o que já existe) | Baixo-médio (passaria a depender da Platform) | Médio (novo processo/porta) | Médio (novo writer) | Médio-alto (novo publisher) |
| Impacto no Laboratório 369 | Baixo (1 leitor simples) | Médio (adota dependência da Platform) | Baixo (1 cliente HTTP) | Médio (driver de banco) | Médio-alto (consumer) |
| Adequado ao estágio atual (single machine, batch, baixo volume) | Sim | Parcial | Não | Não | Não |
| Reversibilidade/migração futura | Alta (contrato = nome/local de arquivo) | — (já é o alvo final) | Média | Baixa | Baixa |

---

## Alternativa 1 — Leitura direta de arquivos (filesystem)

**Descrição:** O Laboratório 369 recebe, via configuração, o caminho para `storage/output` do Collector369 (mesma máquina) e lê o arquivo mais recente por provider, seguindo a convenção de nome já implementada em `CollectorStorage` (`{provider}_{timestamp}.{ext}`).

**Vantagens:**
- Zero infraestrutura nova; usa exatamente o diretório que o Collector369 já rotula como "saída oficial de entrega ao Laboratório 369" desde a Sprint 4.
- Não exige nenhuma alteração no Collector369 — atende integralmente à restrição da Sprint 10 de preservar sua arquitetura.
- Menor tempo até satisfazer o critério de sucesso da sprint.
- Contrato de integração é só "nome e local de arquivo" — barato de documentar, barato de migrar depois para a Alternativa 2 se/quando a L369 Platform for adotada por mais projetos.

**Desvantagens:**
- Acopla os dois projetos via caminho de disco compartilhado — só funciona enquanto ambos rodam na mesma máquina.
- Não usa a L369 Platform como camada de integração, o que diverge do papel que a ADR-001 atribui a ela.

**Aderência aos princípios L369:** Média — respeita a separação de responsabilidades entre Collector369 (coleta) e Laboratório 369 (pesquisa), mas não usa o ponto de integração institucionalmente "correto" (Platform).

**Riscos:** Se os dois projetos passarem a rodar em máquinas/containers diferentes, o caminho compartilhado deixa de funcionar e precisa ser revisto.

**Recomendação técnica individual:** Adequada como solução imediata, dado o estágio atual de ambos os projetos.

---

## Alternativa 2 — Integração mediada pela L369 Platform

**Descrição:** Construir (ou reaproveitar) um componente de storage/integração na L369 Platform — que já possui `StorageInterface`/`LocalStorage` em `core/Shared` e um slot reservado em `providers/Storage/` — e fazer tanto o Collector369 quanto o Laboratório 369 dependerem dele para, respectivamente, publicar e consumir os arquivos coletados.

**Vantagens:**
- É o desenho institucionalmente "correto" segundo a ADR-001, que lista "integrações" como responsabilidade explícita da L369 Platform.
- Reaproveita infraestrutura já existente (`StorageInterface`, `LocalStorage`) em vez de criar um mecanismo paralelo.
- Caminho natural de evolução se outros projetos precisarem consumir saídas de Providers no futuro.

**Desvantagens:**
- **Nenhum projeto do ecossistema hoje depende da L369 Platform em produção** — nem o próprio Collector369 (que construiu seu próprio `ProviderRegistry`/`ProviderResolver` na Sprint 8 em vez de reaproveitar o framework de Providers já existente na Platform). Adotar a Platform nesta sprint significa abrir essa frente pela primeira vez, sem precedente testado.
- Exigiria decidir e configurar como o Laboratório 369 (e possivelmente o Collector369) passam a consumir um pacote composer local/via VCS da Platform — trabalho de integração que extrapola o objetivo único desta sprint.
- Maior complexidade e tempo de implementação, com risco de a sprint não atender ao critério de sucesso no prazo.

**Aderência aos princípios L369:** Alta — é o único caminho que usa a Platform exatamente como a ADR-001 prevê.

**Riscos:** Risco de sobre-engenharia para o problema atual (mover 2 arquivos `.xlsx` por execução, em lote, na mesma máquina); risco de a sprint estourar escopo ao mexer em um terceiro repositório (L369 Platform) não mencionado nas instruções da Sprint 10.

**Recomendação técnica individual:** É o destino arquitetural correto a médio prazo, mas prematuro como primeira entrega — não há ainda nenhum projeto validando esse caminho na prática.

---

## Alternativa 3 — API HTTP exposta pelo Collector369

**Descrição:** O Collector369 sobe um servidor HTTP (ou endpoint simples) que expõe os arquivos/metadados mais recentes por provider; o Laboratório 369 consome via requisições HTTP.

**Vantagens:** Desacopla fisicamente os dois projetos (não exigem mais estar na mesma máquina/disco); é o mecanismo mais formal entre os avaliados.

**Desvantagens:** Collector369 é hoje um CLI (`bin/collector369`), sem nenhum componente de servidor; introduzir um processo HTTP long-running é uma mudança arquitetural real no Collector369, o que a Sprint 10 proíbe explicitamente. Exige also autenticação, versionamento de contrato e disponibilidade contínua — infraestrutura desproporcional ao volume atual (poucos arquivos por dia).

**Recomendação técnica individual:** Não recomendada nesta sprint — viola a restrição de não alterar a arquitetura do Collector369 e não se justifica pelo volume/frequência atuais.

---

## Alternativa 4 — Banco de dados compartilhado

**Descrição:** Collector369 grava os dados coletados (ou metadados dos arquivos) em um banco de dados acessível também pelo Laboratório 369.

**Vantagens:** Consultas estruturadas, histórico centralizado.

**Desvantagens:** Nenhum dos dois projetos usa banco de dados hoje; exigiria provisionar um SGBD, desenhar schema e escrever migrations só para esta integração. Acopla ambos os projetos a um schema compartilhado — qualquer mudança de formato exige coordenação entre os dois lados. Nenhuma das restrições da sprint (preservar `CollectorStorage`, não criar regra de negócio) se encaixa bem com introduzir uma camada de persistência estruturada, que tende a carregar junto decisões de modelagem (que já é, em si, uma forma de interpretação dos dados).

**Recomendação técnica individual:** Não recomendada nesta sprint — infraestrutura e complexidade desproporcionais ao problema, e risco de ultrapassar o limite "Collector369 não interpreta dados".

---

## Alternativa 5 — Fila/barramento de eventos

**Descrição:** Collector369 publica um evento ("novo arquivo coletado") em uma fila; o Laboratório 369 consome essa fila de forma assíncrona.

**Vantagens:** Notificação em tempo quase real; desacopla produtor/consumidor.

**Desvantagens:** Exige um broker (mesmo que a L369 Platform já tenha um `InMemoryQueue`/`InMemoryEventBus`, ambos são implementações em memória de processo único, inúteis para comunicação entre dois processos/repositórios distintos — um broker real seria uma peça de infraestrutura totalmente nova). Complexidade e superfície operacional muito acima do necessário para "mover um arquivo `.xlsx` gerado em lote, algumas vezes por dia".

**Recomendação técnica individual:** Não recomendada nesta sprint — maior complexidade entre todas as alternativas, sem ganho proporcional dado o volume/frequência atuais.

---

## Recomendação

**Adotar a Alternativa 1 (leitura direta de arquivos) agora, registrando explicitamente a Alternativa 2 (integração mediada pela L369 Platform) como o destino arquitetural de médio prazo.**

Justificativa: a Alternativa 1 é a única que satisfaz o critério de sucesso da sprint sem violar nenhuma das restrições explícitas (preservar Collector369 integralmente, não implementar novos Providers, não implementar regra de negócio/Checklist/indicadores/IA) e sem introduzir infraestrutura não justificada pelo volume atual (poucos arquivos por execução, mesma máquina, processamento em lote). O próprio Collector369 já foi construído, desde a Sprint 4, apontando para exatamente esse diretório como ponto de entrega — a Alternativa 1 apenas completa um contrato que já existia implicitamente.

A Alternativa 2 é reconhecida aqui como o desenho institucionalmente correto segundo a ADR-001, mas depende de uma decisão maior — adoção da L369 Platform por projetos reais — que nenhum projeto do ecossistema tomou até hoje (nem o próprio Collector369) e que está fora do escopo desta sprint. Fica registrada como trabalho futuro, sem compromisso de prazo.

O contrato entre as partes, caso a Alternativa 1 seja aprovada, seria:

- **Localização:** `OUTPUT_PATH/{provider}/` do Collector369 (hoje `./storage/output/{provider}/`), informada ao Laboratório 369 via configuração (não hardcoded).
- **Convenção de nome:** `{provider}_{YYYY-MM-DD_His}.{extensão}` — já implementada e estável em `CollectorStorage::store()`, sem necessidade de alteração.
- **Seleção do arquivo:** o Laboratório 369 lê o arquivo mais recente por provider (mesmo critério já usado pelo `InvestingProvider` para localizar arquivos em `storage/incoming/`).
- **Formato:** planilha `.xlsx`, sem nenhuma interpretação de colunas/indicadores pelo lado do Laboratório 369 nesta sprint — apenas leitura e disponibilização tabular bruta.

---

## Encerramento

Este documento apresenta cinco alternativas e uma recomendação explícita (Alternativa 1, com Alternativa 2 registrada como destino futuro). Nenhuma implementação foi realizada até a aprovação por Renan (Product Owner).

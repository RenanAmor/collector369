# Arquitetura Multiprovider do Collector369

## 1. Contexto

A pesquisa técnica das sprints anteriores demonstrou que nenhum provedor de cotações único (BRAPI, Twelve Data) cobre integralmente a Lista Oficial de Ativos Monitorados. O Collector369 passa a adotar oficialmente uma **arquitetura multiprovider**: múltiplos Providers coexistindo, cada um responsável por um subconjunto de ativos ou por uma fonte específica, sem que isso exija alterar o núcleo do sistema a cada novo Provider adicionado.

## 2. Auditoria da arquitetura atual

Antes de desenhar qualquer coisa nova, foi auditado o que já existe:

- **`CollectorProviderInterface`** já é um contrato mínimo e genérico (`collect(): CollectedFile`) — não amarra nenhum Provider a uma fonte específica.
- **`CollectorManager`** já recebe `array<string, WorkflowRunner>` no construtor e despacha por nome (`run(string $provider)`) — **já era estruturalmente multiprovider desde a Sprint 4**, embora só um Provider estivesse registrado até agora.
- **`WorkflowRunner`** já opera sobre um único Provider por instância (extração → validação → armazenamento) — o padrão correto é ter uma instância de `WorkflowRunner` por Provider registrado, não uma única instância multiplexando vários.
- **Lacuna real identificada:** não existia um ponto único e formal para *registrar* Providers (a montagem era feita inline em `CollectorConsole`, funcional para 1 Provider mas não pensada para crescer) e não existia nenhum mecanismo para *rotear um ativo até o Provider responsável por ele*.

Conclusão da auditoria: a fundação multiprovider **não exigia reescrever o núcleo** — exigia apenas duas peças novas de infraestrutura, descritas abaixo.

## 3. Arquitetura oficial multiprovider

Duas novas classes, ambas em `app/Collectors/`, peers de `CollectorManager`:

### `ProviderRegistry`

Registro central de Providers, indexado por nome. Métodos: `register(string $name, CollectorProviderInterface $provider)`, `get(string $name)`, `has(string $name)`, `names(): array`. Lança `CollectorException` ao registrar um nome duplicado ou buscar um nome inexistente.

### `ProviderResolver`

Determina qual Provider deve atender um ativo específico, a partir de um mapeamento **recebido como configuração** (`array<string, string>`, ativo → nome do Provider). Não interpreta o ativo, não valida sua existência de forma alguma além de checar se há uma entrada mapeada — é puro roteamento técnico, não regra de negócio. Se o ativo não estiver mapeado, lança `CollectorException`.

## 4. Como um novo Provider é adicionado (sem tocar no núcleo)

```php
$registry = new ProviderRegistry();
$registry->register('investing', new InvestingProvider(...));
$registry->register('twelvedata', new TwelveDataProvider(...)); // exemplo futuro

$manager = new CollectorManager($workflows); // constrói um WorkflowRunner por nome registrado
```

Nenhuma linha de `CollectorProviderInterface`, `WorkflowRunner`, `CollectorManager`, `BrowserManager`, `DownloadManager`, `FileValidator` ou `CollectorStorage` precisa mudar. Apenas `CollectorConsole` (composição/wiring, não núcleo) registra o novo Provider e o disponibiliza ao `CollectorManager` — exatamente o mesmo padrão já usado para o `investing`.

## 5. Seleção de Provider por ativo — estado atual

O `ProviderResolver` existe e está testado, mas **ainda não está conectado a nenhum fluxo real do CLI**, porque hoje não existe nenhum comando que opere "por ativo" (o único comando existente, `collect:investing`, opera no nível de Provider inteiro, não de ativo individual). Ele fica pronto para uso assim que um Provider de cotações por ativo (ex.: Twelve Data) for de fato integrado — a integração em si **não faz parte desta sprint**.

## 6. O que esta sprint NÃO fez (propositalmente)

- Nenhuma integração com Twelve Data, BRAPI ou qualquer outro Provider real de cotações.
- Nenhuma regra de negócio, checklist, indicador ou inteligência.
- Nenhuma alteração em `CollectorProviderInterface`, `WorkflowRunner`, `CollectorManager`, `BrowserManager`, `DownloadManager`, `FileValidator` ou `CollectorStorage`.

## 7. Próximos passos (fora do escopo desta sprint)

Quando um Provider de cotações real for aprovado para implementação, ele deverá: (1) implementar `CollectorProviderInterface` normalmente; (2) ser registrado no `ProviderRegistry` via `CollectorConsole` (ou um bootstrap equivalente); (3) opcionalmente, ter suas entradas adicionadas ao mapeamento consumido pelo `ProviderResolver`, se a seleção por ativo entrar em uso naquele momento.

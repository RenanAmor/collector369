# Extensão do Collector369

Fundação da extensão de navegador do Collector369 (Ecossistema L369), criada na Sprint 7.

Reproduzirá, em sprints futuras, o fluxo de coleta hoje realizado manualmente/pelo Automa, integrado ao pipeline de validação e armazenamento já existente no Collector369.

## Estrutura

- `manifest.json` — configuração da extensão (Manifest V3).
- `background/` — service worker (processo em segundo plano).
- `content-scripts/` — scripts injetados em páginas de origem (reservado, vazio nesta sprint).
- `shared/` — contratos e utilitários compartilhados entre os componentes da extensão.

## Escopo desta sprint

Apenas fundação arquitetural: estrutura de diretórios, Manifest V3, scripts base vazios e contrato de mensagens previsto. Nenhuma lógica de coleta, nenhuma automação, nenhuma referência a providers específicos.

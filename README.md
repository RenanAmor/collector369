# Collector369

Infraestrutura oficial de coleta de dados do Ecossistema L369.

## Visão Geral

O Collector369 é um **framework de coleta de dados** projetado para orquestrar a extração, validação e armazenamento de informações financeiras de múltiplas fontes.

## Providers Suportados

- Investing.com
- TradingView
- Yahoo Finance
- Banco Central do Brasil
- B3

## Estrutura do Projeto

```
collector369/
├── app/            # Código-fonte da aplicação
├── storage/        # Armazenamento local (downloads, temp, logs, cache)
├── docs/           # Documentação técnica
├── tests/          # Testes automatizados
└── vendor/         # Dependências (Composer)
```

## Requisitos

- PHP 8.2+
- Composer
- Node.js 18+
- Playwright

## Status

Em construção — Sprint 2 (Esqueleto Arquitetural)

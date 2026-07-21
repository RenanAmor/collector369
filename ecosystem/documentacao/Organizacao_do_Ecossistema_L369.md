# Organização do Ecossistema L369

---

**Versão:** 1.0.0

**Status:** Oficial

**Data:** 18/07/2026

---

# Objetivo

Definir a organização física e lógica do Ecossistema L369.

Toda nova aplicação, biblioteca ou documentação deverá respeitar esta estrutura.

---

# Estrutura Oficial

```text
L369/
│
├── ecosystem/
│   ├── arquitetura/
│   ├── decisoes/
│   │   └── ADR/
│   ├── documentacao/
│   ├── padroes/
│   └── roadmaps/
│
├── laboratorio369/
│   ├── assets/
│   ├── benchmarks/
│   ├── datasets/
│   ├── diagrams/
│   ├── docs/
│   ├── experiments/
│   ├── notebooks/
│   ├── prompts/
│   └── prototypes/
│
├── l369-platform/
│
├── collector369/
│
├── pulsar-rh/
│
├── projeto-chef/
│
├── investimentos369/
│
├── projeto-viva/
│
├── apcompy/
│
├── scripts/
│
├── backups/
│
└── workspace/
```

---

# Responsabilidade de Cada Diretório

## ecosystem

Governança oficial do Ecossistema.

Contém:

- Constituição;
- Arquitetura;
- ADRs;
- Roadmaps;
- Padrões;
- Documentação.

---

## laboratorio369

Pesquisa e Desenvolvimento.

Contém:

- estudos;
- protótipos;
- experimentos;
- benchmarks;
- IA;
- provas de conceito.

---

## l369-platform

Biblioteca compartilhada utilizada por todos os projetos.

---

## Projetos

Cada diretório representa uma aplicação independente.

Todos compartilham arquitetura, mas possuem autonomia de desenvolvimento.

---

## scripts

Scripts utilitários utilizados pelo ecossistema.

---

## backups

Backups locais.

---

## workspace

Arquivos relacionados ao ambiente de desenvolvimento.

---

# Regra Geral

Todo novo diretório criado deverá possuir responsabilidade claramente definida.

Nenhuma funcionalidade poderá existir em duas áreas diferentes.

---

**Fim do Documento**
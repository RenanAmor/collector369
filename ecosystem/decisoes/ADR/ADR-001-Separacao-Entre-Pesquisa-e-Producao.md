# ADR-001 — Separação entre Pesquisa e Produção

---

**Status:** Aceito

**Data:** 18/07/2026

**Versão:** 1.0.0

---

# Contexto

O Ecossistema L369 possui múltiplos projetos compartilhando conhecimento, componentes e arquitetura.

Era necessário definir uma forma de impedir que pesquisas, experimentos e provas de conceito fossem confundidos com código de produção.

---

# Decisão

Foi estabelecida a separação oficial entre quatro áreas distintas.

## Ecosystem

Responsável pela governança.

Armazena:

- Constituição;
- Arquitetura;
- ADRs;
- Padrões;
- Roadmaps;
- Documentação oficial.

Não possui código de produção.

---

## Laboratório 369

Responsável por:

- pesquisa;
- protótipos;
- experimentos;
- benchmarks;
- IA;
- provas de conceito.

Nenhum código do Laboratório deverá ser considerado produção sem passar pelo processo oficial de validação.

---

## L369 Platform

Responsável pelos componentes compartilhados utilizados pelos projetos.

Exemplos:

- autenticação;
- providers;
- engines;
- módulos reutilizáveis;
- integrações;
- infraestrutura comum.

---

## Projetos

Responsáveis pela entrega das aplicações finais.

Exemplos:

- Collector369;
- Pulsar RH;
- Projeto Chef;
- Investimentos369;
- Projeto Viva;
- ApCompy.

Cada projeto utiliza a plataforma, mas permanece independente.

---

# Consequências

Benefícios:

- separação clara entre pesquisa e produção;
- redução de riscos;
- maior organização;
- maior reutilização;
- documentação centralizada;
- arquitetura escalável.

Custos:

- necessidade de documentação contínua;
- disciplina na evolução da arquitetura.

---

# Situação

Esta decisão passa a fazer parte da arquitetura oficial do Ecossistema L369.

Toda alteração deverá gerar um novo ADR.

---

**Fim do Documento**
# Método Renan × ChatGPT × Copilot

---

**Versão:** 1.0.0

**Status:** Oficial

**Data:** 18/07/2026

---

# Objetivo

Estabelecer o fluxo oficial de desenvolvimento utilizado em todos os projetos do Ecossistema L369.

Este método define claramente a responsabilidade de cada participante durante o ciclo de desenvolvimento.

---

# Princípio Fundamental

Arquitetura e execução possuem responsabilidades diferentes.

Misturar essas responsabilidades reduz a qualidade das decisões, aumenta retrabalho e dificulta a evolução dos projetos.

---

# Papéis

## Renan

Responsável por:

- definir os objetivos;
- validar as decisões;
- aprovar entregas;
- priorizar tarefas;
- conduzir a visão do ecossistema.

---

## ChatGPT

Responsável por:

- arquitetura;
- estratégia;
- organização;
- documentação;
- definição de padrões;
- análise técnica;
- elaboração das tarefas;
- validação do processo.

Não executa alterações diretamente no código dos projetos.

---

## Copilot / Codex

Responsável por:

- analisar o código existente;
- implementar alterações;
- executar testes;
- corrigir erros;
- realizar commits;
- realizar push;
- validar a branch correta;
- confirmar atualização da origin.

---

# Fluxo Oficial

```text
Objetivo
      │
      ▼
Arquitetura
      │
      ▼
Definição da tarefa
      │
      ▼
Execução (Copilot/Codex)
      │
      ▼
Testes
      │
      ▼
Commit
      │
      ▼
Push
      │
      ▼
Validação
      │
      ▼
Nova tarefa
```

---

# Regras de Execução

Durante a execução:

- uma tarefa por vez;
- um objetivo por vez;
- nenhuma expansão de escopo;
- nenhuma nova ideia durante a implementação;
- nenhuma alteração fora da tarefa definida.

Novas ideias deverão ser registradas e tratadas posteriormente.

---

# Critérios de Conclusão

Uma tarefa somente será considerada concluída quando:

- implementação finalizada;
- testes executados;
- documentação atualizada;
- commit realizado;
- push realizado;
- validação concluída.

---

# Lições Aprendidas

Problemas já resolvidos não deverão ser repetidos.

Sempre que um erro operacional gerar aprendizado permanente, o método deverá ser atualizado para evitar sua recorrência.

---

# Evolução

Este método poderá evoluir continuamente, preservando seus princípios fundamentais.

---

**Fim do Documento**
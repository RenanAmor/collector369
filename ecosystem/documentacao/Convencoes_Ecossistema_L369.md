# Convenções do Ecossistema L369

---

**Versão:** 1.0.0

**Status:** Oficial

**Data:** 18/07/2026

---

# Objetivo

Padronizar convenções utilizadas em todos os projetos do Ecossistema L369.

Estas convenções reduzem ambiguidades, aumentam a previsibilidade e facilitam a manutenção.

---

# Idioma

A documentação oficial será escrita em português.

Código-fonte utilizará nomenclatura em inglês.

Exemplos:

- UserService
- RecipeEngine
- AIProvider
- ContextBuilder

---

# Estrutura de Pastas

Utilizar letras minúsculas.

Quando necessário utilizar hífen.

Exemplo:

```text
projeto-chef
collector369
l369-platform
```

---

# Arquivos

Documentação:

```text
README.md

Arquitetura_Oficial.md

Roadmap.md
```

Documentos institucionais:

```text
Constituicao_Ecossistema_L369.md

Padroes_Oficiais_L369.md
```

---

# Classes

Utilizar PascalCase.

```text
RecipeEngine

UserController

AuthenticationService

PromptBuilder
```

---

# Interfaces

Sempre iniciar com I.

```text
IRepository

ILogger

IAIProvider
```

---

# Métodos

Utilizar camelCase.

```text
createUser()

generateContext()

calculateIndicators()

saveRecipe()
```

---

# Variáveis

Utilizar camelCase.

```text
userName

recipeId

currentProvider
```

---

# Constantes

Utilizar UPPER_SNAKE_CASE.

```text
DEFAULT_TIMEOUT

MAX_USERS

API_VERSION
```

---

# Branches

Quando necessário:

```text
main

develop

feature/

fix/

hotfix/
```

---

# Commits

Preferencialmente descritivos.

Exemplo:

```text
feat: adiciona módulo de autenticação

fix: corrige cálculo de indicadores

docs: atualiza arquitetura

refactor: reorganiza serviços
```

---

# Documentação

Todo projeto deverá conter:

- README;
- arquitetura;
- roadmap;
- documentação técnica;
- histórico relevante quando necessário.

---

# Inteligência Artificial

Toda integração deverá ser desacoplada.

A IA nunca deverá conter regras de negócio permanentes.

---

# Evolução

Novas convenções deverão ser adicionadas a este documento sempre que forem oficializadas.

---

**Fim do Documento**
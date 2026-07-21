# Guia de Organização dos Projetos

---

**Versão:** 1.0.0

**Status:** Oficial

**Data:** 18/07/2026

---

# Objetivo

Definir a estrutura mínima obrigatória para qualquer projeto desenvolvido no Ecossistema L369.

Todos os projetos deverão manter uma organização consistente, facilitando manutenção, escalabilidade e reutilização.

---

# Estrutura Recomendada

```text
projeto/
│
├── app/
├── bootstrap/
├── config/
├── database/
├── docs/
├── public/
├── resources/
├── routes/
├── storage/
├── tests/
├── vendor/
│
├── .env.example
├── composer.json
├── README.md
└── LICENSE
```

A estrutura poderá variar conforme a tecnologia utilizada, desde que preserve organização e responsabilidade clara.

---

# Diretórios

## app/

Contém toda a regra de negócio.

---

## config/

Arquivos de configuração.

---

## database/

Migrations, seeders e estruturas do banco.

---

## docs/

Documentação específica do projeto.

Exemplos:

- arquitetura;
- roadmap;
- API;
- banco de dados.

---

## public/

Ponto de entrada da aplicação.

---

## resources/

Arquivos estáticos, templates e recursos.

---

## routes/

Definição das rotas da aplicação.

---

## storage/

Arquivos temporários, logs e cache.

---

## tests/

Testes automatizados.

---

# README

Todo projeto deverá possuir um README contendo:

- objetivo;
- instalação;
- configuração;
- execução;
- estrutura;
- dependências;
- roadmap.

---

# Documentação

Cada projeto deverá manter sua documentação atualizada.

Nenhuma decisão importante deverá existir apenas no código.

---

# Versionamento

Os projetos utilizarão Git com histórico organizado.

Commits deverão ser descritivos.

---

# Independência

Cada projeto deverá permanecer independente dos demais.

Dependências compartilhadas deverão ser implementadas na L369 Platform.

---

# Evolução

Mudanças estruturais relevantes deverão ser refletidas na documentação oficial do Ecossistema.

---

**Fim do Documento**
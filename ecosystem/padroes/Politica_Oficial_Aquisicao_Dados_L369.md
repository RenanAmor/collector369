# Política Oficial de Aquisição de Dados do Ecossistema L369

---

**Versão:** 1.0.0
**Status:** Oficial
**Data:** 22/07/2026

---

# Objetivo

Estabelecer os princípios e critérios permanentes que orientam **como qualquer projeto do Ecossistema L369 adquire dados de fontes externas**, independentemente da tecnologia, do fornecedor ou do domínio de negócio envolvido.

Esta política é institucional e não técnica. Ela não prescreve implementação, ferramenta ou linguagem — define o que é aceitável, em que ordem de preferência, e sob quais critérios, para qualquer Provider de dados presente ou futuro no ecossistema.

---

# 1. Princípios Gerais de Aquisição de Dados

- A aquisição de dados deve priorizar **confiabilidade e previsibilidade** acima de velocidade de implementação.
- Toda fonte de dados deve ser tratada como uma **dependência externa de risco**, cuja instabilidade não pode comprometer a integridade do ecossistema.
- Nenhum projeto deve depender de uma única fonte de dados sem alternativa conhecida ou documentada.
- A relação com qualquer fonte de dados deve ser a mais formal possível dentro do que for viável — contrato e documentação oficial sempre são preferíveis a acesso não formalizado.
- Nenhum mecanismo de aquisição de dados deve ser projetado para **contornar deliberadamente controles de segurança ou proteções anti-automação** impostos pelo próprio provedor da fonte. Quando uma fonte impõe esse tipo de proteção, isso é um sinal institucional de que a fonte não deseja acesso automatizado não contratado — e deve ser tratado como tal na escolha da estratégia de aquisição.
- Toda decisão de aquisição de dados deve ser registrada e rastreável, para permitir auditoria futura sobre por que determinada fonte e método foram escolhidos.

---

# 2. Ordem de Preferência das Fontes de Dados

Ao selecionar como um Provider deve obter dados de uma fonte externa, a ordem de preferência institucional é:

1. **API oficial ou contratada** — acesso formalizado, documentado e sancionado pelo provedor dos dados.
2. **Fonte paga com relação contratual (SLA)** — quando não existe API oficial gratuita, mas existe fornecimento formal mediante pagamento.
3. **Automação de interface (scraping/automação de navegador)** — apenas quando as opções acima não existem ou são inviáveis, e desde que não configure contorno de proteção anti-automação da fonte (ver item 1).
4. **Processo semimanual** — quando nenhuma das opções acima é tecnicamente viável, esteja disponível, ou seja desejável no momento, mantendo intervenção humana pontual como parte formal do fluxo.

Essa ordem deve ser reavaliada periodicamente para cada Provider — uma fonte que hoje só permite automação de interface ou processo semimanual pode, no futuro, passar a oferecer API ou acesso pago, e a migração para uma opção de maior preferência deve ser considerada.

---

# 3. Critérios para Utilização de APIs Oficiais

Uma API oficial é elegível como fonte de dados quando:

- é documentada publicamente pelo provedor;
- possui contrato de uso claro (mesmo que gratuito), incluindo limites de uso;
- possui versionamento e política de descontinuação conhecida;
- permite autenticação e rastreabilidade de uso.

APIs oficiais são a opção institucionalmente preferida e não exigem justificativa adicional para adoção, além da validação técnica de que atendem aos dados necessários.

---

# 4. Critérios para Utilização de Fontes Pagas

A adoção de uma fonte paga deve ser justificada por:

- ausência de API gratuita equivalente;
- criticidade dos dados para o produto que os consome;
- volume e frequência de uso que tornem o custo proporcional ao valor entregue;
- existência de contrato formal com SLA e canal de suporte.

Custos recorrentes devem ser formalmente orçados e aprovados antes da adoção, e não devem ser tratados como decisão puramente técnica.

---

# 5. Critérios para Utilização de Automação de Interface

Automação de interface (navegação, scraping, automação de navegador) só deve ser adotada quando:

- não existe API oficial nem fonte paga viável para os mesmos dados;
- a fonte não impõe proteção ativa contra automação — quando impõe, isso é um sinal para reconsiderar a fonte ou o método, não para investir em contornar a proteção;
- o mecanismo de automação é isolado como um componente próprio e documentado, sem se misturar à lógica de negócio do produto consumidor;
- existe um plano de contingência documentado para o caso de a fonte alterar sua interface ou bloquear o acesso.

Automação de interface é uma opção tecnicamente legítima, mas institucionalmente mais frágil que as duas anteriores, e deve ser tratada como tal na priorização de investimento de manutenção.

---

# 6. Critérios para Utilização de Processos Semimanuais

Um processo semimanual é aceitável, de forma transitória ou permanente, quando:

- as opções automatizadas não são viáveis, disponíveis, ou desejáveis no momento da decisão;
- a etapa manual é claramente delimitada, documentada, e possui responsável definido;
- a etapa manual entrega os dados em um formato e local que permitam que o restante do pipeline (validação, armazenamento, entrega) opere de forma automatizada, sem exigir automação também nessa parte.

Processos semimanuais não devem ser tratados como falha de arquitetura — são uma opção institucional válida quando as alternativas superiores não se aplicam.

---

# 7. Critérios para Homologação de Novos Providers

Todo novo Provider de dados, em qualquer projeto do ecossistema, deve ser homologado mediante:

- classificação explícita do tipo de fonte utilizada (API oficial, fonte paga, automação de interface, ou processo semimanual), conforme a ordem de preferência do item 2;
- documentação do porquê a opção escolhida foi adotada em vez das opções de maior preferência, quando aplicável;
- aderência ao contrato de interface padrão de Providers já estabelecido no projeto (ex.: interface comum de coleta);
- validação técnica de que os dados entregues atendem ao formato e à integridade esperados;
- registro da decisão para consulta futura (auditoria e reavaliação periódica).

---

# 8. Critérios de Confiabilidade

Uma fonte de dados é considerada confiável quando:

- possui histórico de disponibilidade estável e previsível;
- permite verificação de integridade dos dados entregues antes de seu uso;
- possui forma de rastrear a origem, o momento e o método de cada coleta;
- não exige que o produto consumidor confie cegamente na exatidão do dado sem alguma camada de validação técnica.

---

# 9. Critérios de Continuidade Operacional

Todo Provider deve considerar:

- existência de um plano de contingência caso a fonte de dados se torne indisponível, mude sua interface, ou revogue o acesso;
- ausência de ponto único de falha não documentado — se a coleta depende de uma única pessoa, máquina, sessão ou credencial, isso deve estar explicitamente registrado como risco;
- tempo aceitável de recuperação em caso de falha da fonte, compatível com a criticidade do dado para o produto consumidor.

---

# 10. Critérios de Independência Tecnológica

O Ecossistema L369 deve evitar, sempre que possível:

- amarrar sua capacidade de coleta a uma única ferramenta ou tecnologia proprietária sem alternativa conhecida;
- depender de uma única pessoa com conhecimento exclusivo sobre como uma fonte é acessada;
- construir mecanismos de aquisição de dados tão específicos a uma fonte que não possam ser reavaliados ou substituídos sem reescrever todo o Provider.

Independência tecnológica não significa evitar tecnologia específica — significa garantir que a substituição de uma fonte ou método seja sempre uma decisão possível, não uma prisão arquitetural.

---

# 11. Evolução

Nenhum critério desta política é imutável.

Toda alteração relevante deverá ser registrada através de ADR e refletida na documentação oficial do Ecossistema L369, conforme a Estrutura de Documentação já estabelecida.

---

**Fim do Documento**

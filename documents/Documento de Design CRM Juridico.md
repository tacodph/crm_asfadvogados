# CRM Jurídico: desenho de um sistema para o ciclo pré-processual

**CRM Jurídico · Documento de design técnico-jurídico**  
Vieira & Sant'Ana · confidencial  
v1.1 · agosto de 2026 · *atualizado com o status da implementação*  
*Sujeito a validação do comitê de ética do escritório*

Documento de design técnico-jurídico para um escritório de médio porte (10 a 50 usuários), com atuação em **consultivo empresarial** e **causas de concursos públicos**, cobrindo do primeiro contato ao contrato assinado — dentro dos limites do Código de Ética e Disciplina da OAB, do Provimento nº 205/2021 do CFOAB e da LGPD.

As seções 1–5 descrevem o **desenho-alvo**. A seção 6 registra o que já foi entregue no código (repositório `crm_asfadvogados`) e o que permanece pendente.

## Premissas assumidas

- Porte médio: 10–50 usuários, sendo ~8 em papel comercial ativo.
- Instância única do escritório no lançamento, com arquitetura preparada para virar SaaS multi-tenant sem reescrita.
- Dois funis distintos e configuráveis: B2B consultivo (ticket alto, ciclo longo) e Pessoa Física em volume (concursos).
- O CRM cobre o momento *comercial e pré-contratual*. Prazos, peças e processos permanecem no sistema de gestão processual, integrado por referência.
- Referências normativas devem ser conferidas em sua redação vigente pelo comitê de ética antes da implementação; este documento aponta a exigência, não substitui o parecer.

---

## 1. Modelagem de negócio e funcionalidades

### 1.1 Funis configuráveis

Funil, etapa, campo obrigatório, SLA e motivo de perda são **dados, não código**: o escritório cria e reordena etapas sem deploy. Cada etapa declara (a) campos obrigatórios para saída, (b) SLA em horas úteis, (c) automações vinculadas e (d) se exige registro de motivo em caso de perda. A regra de ouro é que um lead só avança quando os campos obrigatórios da etapa estão preenchidos — é isso que torna o funil auditável e o dashboard confiável.

| Etapa — funil B2B consultivo | Campos obrigatórios para sair da etapa | SLA |
| --- | --- | --- |
| Prospecção / Inbound | Canal de origem, contato válido, registro de consentimento, responsável atribuído | 4h |
| Diagnóstico / Qualificação | Porte, área jurídica, dor mapeada, decisor identificado, conflito de interesses verificado | 5d |
| Apresentação da solução | Escopo, honorários dentro da tabela da seccional, modelo de minuta selecionado | 7d |
| Negociação | Versão vigente da proposta, objeção classificada, data de decisão acordada | 10d |
| Fechamento | Contrato assinado, procuração, abertura do caso no sistema processual | — |

O funil de concursos herda a mesma máquina, com cadência muito mais curta (Novo lead → Triagem → Envio da oferta → Objeções → Fechamento, SLA de primeiro contato em 15 minutos) e campos próprios: banca, concurso, fase da eliminação, prazo administrativo remanescente. Motivos de perda são uma lista fechada e configurável — o campo livre é opcional e complementar, nunca substituto, porque o relatório de perdas só serve se for categórico.

### 1.2 Captação multicanal e deduplicação

Todos os canais (formulário do site, WhatsApp Business, Instagram/Meta, Google Business Profile, indicação, telefone) escrevem em um único *inbox* de leads. Cada mensagem de entrada gera um evento imutável com canal, identificador externo, carimbo de tempo e o texto do consentimento exibido ao titular naquele momento.

A deduplicação roda em duas camadas: **determinística** (telefone E.164, e-mail normalizado, CPF/CNPJ) resolve automaticamente; **probabilística** (similaridade de nome + mesma empresa + janela de 90 dias) apenas *sugere* a fusão a um humano. Fusão nunca destrói dado: os registros originais permanecem como fontes vinculadas ao contato canônico, o que preserva a rastreabilidade de qual consentimento veio de qual canal — exigência prática da LGPD que a fusão silenciosa quebraria.

### 1.3 Histórico unificado e automação de follow-up

Cada interação (mensagem, ligação, e-mail, reunião, nota) é um registro append-only na linha do tempo do contato. Notas são editáveis apenas por quem as criou e por tempo limitado, com versão anterior preservada.

A automação segue o padrão **gatilho → condição → ação**, com um limite deliberado: as ações que enviam mensagem para o titular exigem template pré-aprovado e passam pelo revisor ético (§2.4). As ações padrão do MVP:

| Gatilho | Condição | Ação |
| --- | --- | --- |
| Novo lead recebido | Canal = WhatsApp, horário comercial | Atribuir por round robin + notificar plantonista (SLA 15 min) |
| Sem resposta há 3 dias | Etapa ≠ Fechamento e consentimento vigente | Criar *tarefa humana* de retomada — não dispara mensagem sozinha |
| 48h antes do vencimento da proposta | Proposta enviada e não respondida | Alertar responsável e sócio da área |
| Lead parado além do SLA da etapa | Qualquer funil | Escalar para o gestor e sinalizar o card |
| Titular revoga consentimento | Qualquer canal | Suspender toda comunicação automática e abrir tarefa para o encarregado |

> **Decisão de design ⇢ exigência ética**  
> O follow-up automático cria **tarefa para o advogado**, não mensagem ao lead. Cadência automatizada de disparos em massa aproxima-se de captação ativa e de mala direta ostensiva, vedadas pelo Provimento 205/2021. Mensagens automáticas ficam restritas a respostas a contato iniciado pelo próprio interessado e a comunicações de serviço (confirmação de reunião, envio de proposta solicitada).

### 1.4 Propostas, minutas e contratos

Propostas nascem de **modelos aprovados** pelo comitê, com cláusulas obrigatórias travadas e campos variáveis (escopo, honorários, parcelamento, índice de reajuste, vigência). Versões são imutáveis: qualquer alteração gera v+1 com autor, carimbo de tempo e diff. Validade, reajuste anual e renovação são datas de primeira classe, com alertas automáticos. Assinatura eletrônica é integração, não funcionalidade própria.

### 1.5 Distribuição, agenda, métricas e indicações

A distribuição combina três estratégias configuráveis por funil: *round robin* simples (volume/PF), roteamento por especialidade (B2B) e balanceamento por carga de trabalho aberta. Sobre elas incidem duas travas obrigatórias: verificação de conflito de interesses antes da atribuição e exclusão de responsáveis em férias/licença.

O dashboard entrega conversão por etapa, tempo médio de fechamento por funil, produtividade e SLA de primeiro contato por colaborador, receita contratada, origem dos leads e ranking de motivos de perda. O módulo de indicações registra quem indicou, o vínculo com o contato indicado e o desfecho — **sem qualquer forma de remuneração, comissão ou bonificação por indicação**, que configuraria captação de clientela; o módulo mede a origem e organiza o agradecimento institucional, nada além disso.

---

## 2. Compliance jurídico e ética profissional

### 2.1 Provimento nº 205/2021 traduzido em regras de produto

O provimento admite marketing jurídico informativo e veda a captação de clientela, a mercantilização e a publicidade imoderada. Um CRM comercial vive exatamente na fronteira, então o desenho adota o princípio de que **o sistema só amplifica o contato que o interessado iniciou**.

| Risco ético | Como o sistema impede |
| --- | --- |
| Captação ativa de clientela | Não há importação de listas nem enriquecimento de contatos de terceiros. Todo lead exige origem rastreável de contato iniciado pelo interessado ou indicação nominal registrada. |
| Mala direta ostensiva | Não existe função de disparo em massa. Envios são 1:1, a partir da ficha do lead, e limitados por cadência máxima configurável (padrão: 3 tentativas, depois encerramento por “sem resposta”). |
| Promessa de resultado | Revisor ético bloqueia publicação de templates com termos de garantia de êxito; propostas usam cláusula padrão de ausência de garantia de resultado. |
| Linguagem mercantilista | Bloqueio de vocabulário promocional (desconto, promoção, imperdível) e de honorários abaixo da tabela da seccional no gerador de propostas. |
| Publicidade comparativa / autoexaltação | Mesma trava aplicada a superlativos e comparações (“melhor escritório”, “nº 1”), com registro do parecer anexado ao template. |

### 2.2 Sigilo profissional: a separação comercial / caso

Esta é a decisão estrutural mais importante do documento. O CRM guarda **dado comercial** — identificação, canal, objeto genérico da consulta, histórico de contato, valores e etapa. Ele **não** guarda conteúdo jurídico: fatos do caso, documentos, estratégia, teses, pareceres. Esse conteúdo vive no sistema de gestão processual/GED e é referenciado por identificador.

Consequência prática: o consultor comercial vê o pipeline inteiro e nada do caso; o advogado responsável vê os dois; o sócio vê tudo; o encarregado de dados vê metadados e a trilha de auditoria, não o conteúdo. Acesso excepcional existe, mas é *break-glass*: exige justificativa, notifica o responsável e fica registrado. Campos de nota livre exibem aviso permanente de que não devem receber conteúdo sigiloso — e o revisor sinaliza indícios (números de processo, nomes de partes contrárias) para revisão.

### 2.3 LGPD: bases legais, retenção e direitos do titular

Base legal por finalidade, não por sistema. Contato comercial em resposta a solicitação do próprio titular: **procedimentos preliminares ao contrato** (art. 7º, V). Comunicações de marketing e reengajamento: **consentimento** (art. 7º, I), coletado com finalidade específica e revogável em um clique. Registros de auditoria e defesa em processo disciplinar: **cumprimento de obrigação legal e exercício regular de direitos**. Dados sensíveis (saúde em causas previdenciárias, dado biométrico em concursos) não entram no CRM comercial.

O consentimento é capturado no canal de origem e armazenado com texto exibido, versão, canal, IP/identificador e carimbo de tempo. Em WhatsApp e Instagram, o opt-in é registrado a partir da mensagem iniciada pelo titular somada ao aviso de privacidade enviado na primeira resposta. Retenção padrão: lead perdido é anonimizado em 24 meses (contadores agregados permanecem para o dashboard); cliente contratado segue a retenção contratual e prescricional. Direitos do titular (acesso, correção, portabilidade, eliminação, revogação) têm fila própria com prazo, responsável e resposta registrada — visível no módulo de compliance do protótipo.

### 2.4 Revisor ético de textos automatizados

Todo template de mensagem, proposta ou follow-up passa por análise antes de entrar em produção: léxico proibido (garantia de êxito, superlativos, apelo promocional, urgência artificial), verificação de identificação profissional do remetente e exigência de finalidade informativa. O resultado é um parecer versionado, anexado ao template — que serve tanto de trava preventiva quanto de prova de diligência. Textos aprovados carregam selo com data e revisor; templates reprovados não podem ser publicados por nenhum perfil, inclusive gestor.

### 2.5 Trilha de auditoria

Log append-only, com retenção mínima de cinco anos e hash encadeado por registro, cobrindo: acesso a ficha de lead, mudança de etapa, alteração de proposta, exportação de dados, tentativa de acesso negada, alteração de automação e de permissão. A trilha é consultável pelo encarregado e pelo comitê, mas não é editável por ninguém — inclusive administradores. Além de salvaguarda ética, é o material probatório do escritório caso a conduta comercial seja questionada.

---

## 3. Arquitetura técnica

### 3.1 Estilo arquitetural: monolito modular

Recomendação original: **monolito modular** com fronteiras internas explícitas (Leads, Funil, Interações, Propostas, Automação, Compliance, Identidade) e um único banco relacional, mais um *worker* assíncrono separado para automações e webhooks.

**Decisão de implementação (v1.1):** o produto está sendo construído como **monólito Laravel** com módulos/controllers por domínio (Contatos, Empresas, Negociações, Admin de catálogos, Tenancy, Auth), UI Inertia/Vue e isolamento multi-tenant por `tenant_id` + *global scope*. Detalhamento em `documents/CRM_MULTITENANT.md` e na seção 6.

*Trade-off aceito:* deploy acoplado — uma alteração no módulo de propostas reimplanta tudo. Mitigação: feature flags e testes de contrato entre módulos.

### 3.2 Modelo de dados

Descrição textual do diagrama ER-alvo. Todas as entidades de negócio devem carregar `tenant_id`, `created_at`, `updated_at` e, no desenho completo, `deleted_at` (exclusão lógica, exceto quando o titular pede eliminação — aí a remoção é física com registro do evento).

| Entidade | Atributos centrais | Relações |
| --- | --- | --- |
| Contato | nome, tipo (PF/PJ), documento, telefones, e-mails, empresa | 1:N Lead · 1:N Consentimento · N:1 Contato canônico (dedupe) |
| Lead | objeto genérico, valor estimado, canal de origem, etapa, motivo de perda, entrada na etapa | N:1 Contato · N:1 Funil · N:1 Etapa · N:1 Usuário (responsável) · 1:N Interação, Proposta, Tarefa |
| Funil / Etapa | nome, ordem, SLA, campos obrigatórios (JSON), exige motivo de perda | Funil 1:N Etapa · Etapa 1:N Lead · Etapa 1:N Automação |
| Interação | tipo, direção, canal, conteúdo, id externo, ocorrida_em (append-only) | N:1 Lead · N:1 Usuário ou Automação (autor) |
| Proposta | código, versão, escopo, honorários, validade, status, modelo de origem | N:1 Lead · 1:N Versão (imutável) · 1:1 Contrato |
| Contrato | assinado_em, vigência, reajuste, renovação, ref. do caso no sistema processual | 1:1 Proposta · N:1 Contato |
| Tarefa | título, prazo, status, origem (humana/automação) | N:1 Lead · N:1 Usuário |
| Automação | gatilho, condições (JSON), ações (JSON), ativa, parecer ético | N:1 Funil/Etapa · 1:N Execução · N:1 Template |
| Usuário | nome, OAB, especialidades, capacidade, perfil (RBAC), status | 1:N Lead · 1:N Tarefa · 1:N Evento de auditoria |
| Consentimento | finalidade, base legal, texto exibido, canal, coletado_em, revogado_em | N:1 Contato |
| EventoAuditoria | ator, ação, entidade, diff, hash anterior, ocorrido_em | N:1 Usuário · polimórfico com todas as entidades |

**No código atual**, a entidade operacional de pipeline é **`Negociacao`** (não há tabela `leads` separada). Consentimentos por finalidade já existem (`consentimentos_contato` + catálogos). Histórico de negociação existe (`historicos_negociacao`), ainda sem append automático em toda mudança de etapa. Proposta, Contrato, Tarefa, Automação e EventoAuditoria **ainda não** foram modelados.

### 3.3 Stack e trade-offs

**Desenho original (§3.3):** NestJS ou Django + React/Next.js; PostgreSQL com RLS/`pgcrypto`/`pg_trgm`; Redis + BullMQ; região São Paulo.

**Stack adotada na implementação:**

| Camada | Escolha |
| --- | --- |
| Backend | PHP 8.4 · **Laravel 13** |
| UI | **Inertia.js v3** + **Vue 3** + Tailwind CSS 4 |
| Rotas tipadas no front | Laravel Wayfinder |
| Auth | Laravel Fortify (login, registro, verificação de e-mail, 2FA, passkeys) |
| Banco | **PostgreSQL** (Herd local; schema de negócio + catálogo IBGE em `base_dados_ibge`) |
| Testes / qualidade | Pest, Pint, Larastan |
| Multi-tenant | Subdomínio `{slug}.crm-asfadvogados.test` + `tenant_id` + Eloquent `TenantScope` |

*Trade-off:* a stack Laravel/Inertia acelera o MVP comercial e o multi-tenant; RLS, criptografia de coluna e fila dedicada de automações ficam para fases seguintes.

**Integrações (ainda no desenho):** WhatsApp Business Cloud API via BSP; e-mail/calendário; webhooks do site; Instagram/Meta; assinatura eletrônica; referência ao sistema processual — nenhuma dessas integrações está ligada ao CRM em produção de código.

### 3.4 Segurança e multi-tenant

RBAC com quatro perfis base (gestor, advogado responsável, consultor comercial, encarregado/DPO) e escopo por funil e por carteira permanece **meta de produto**. Hoje há apenas `role` simples no usuário (`owner` / `member`) após o onboarding.

Multi-tenant desde o primeiro dia no modelo lógico: banco compartilhado e `tenant_id` em todas as tabelas de negócio — **já implementado** via middleware de resolução por subdomínio e *global scope* (fail-closed). PostgreSQL RLS, MFA obrigatório por perfil, criptografia de coluna e *break-glass* ainda não.

Detalhes conceituais e opções de isolamento: ver `documents/CRM_MULTITENANT.md`.

---

## 4. Roadmap e riscos

| Fase | Escopo | Prazo | Status (ago/2026) |
| --- | --- | --- | --- |
| **MVP** | Dois funis configuráveis; contatos/empresas; board de negociações; distribuição configurável; consentimento; admin de catálogos; multi-tenant; auditoria e dashboard reais | 3–4 meses | **Parcial** — base comercial e funil em andamento (ver §6) |
| **Fase 2** | Propostas versionadas e minutas; assinatura eletrônica; revisor ético; portal do titular; Instagram/Google Business; calendário bidirecional | +3 meses | Não iniciado |
| **Fase 3** | Indicações; dedupe probabilística; integração processual; previsão de receita; multi-tenant comercial e onboarding self-service completo | +4 meses | Onboarding self-service de tenant **iniciado**; restante não |

| Risco | Mitigação |
| --- | --- |
| Representação disciplinar por captação irregular | Ausência de disparo em massa, revisor ético obrigatório, parecer versionado e trilha de auditoria como prova de diligência; validação prévia do desenho pelo comitê e, se possível, consulta à seccional |
| Vazamento de dado sigiloso pelo CRM | Separação comercial/caso, criptografia de coluna, break-glass auditado e detecção de conteúdo sigiloso em campos livres |
| Bloqueio ou mudança de política do WhatsApp | BSP homologado, camada de canal abstrata e fallback por e-mail/telefone; nunca depender de número pessoal |
| Baixa adoção pelos advogados | Captura automática de interações (nada de digitação manual), campos obrigatórios mínimos por etapa e piloto com uma área antes do rollout |
| Multi-tenant tardio | `tenant_id` e (futuro) RLS desde a primeira migração, mesmo com um único escritório em produção — **tenant_id já presente** |

---

## 5. Perguntas em aberto

1. Volume mensal atual de leads por canal e taxa de conversão de referência — dimensiona fila, SLA e o desenho do funil PF.
2. Quantos usuários efetivamente comerciais entre os 10–50, e quem responde pelo pipeline no dia a dia?
3. Já existe sistema de gestão processual em uso (Astrea, Projuris, ADVBOX, próprio)? Qual API disponível?
4. Há encarregado (DPO) designado e política de privacidade publicada? Existe registro de operações de tratamento?
5. O número de WhatsApp é único do escritório ou há números pessoais em uso? Migração é aceitável?
6. Orçamento e janela de lançamento: construir sob medida ou avaliar CRM de mercado com camada de compliance?
7. O comitê de ética valida o léxico bloqueado proposto, ou prefere lista própria aprovada em ata?
8. A ambição de SaaS tem horizonte definido? Isso muda a prioridade de onboarding, billing e suporte.

---

## 6. Status da implementação (atualização v1.1)

Registro do que o código do projeto já cobre em relação a este documento. Ambiente local típico: Laravel Herd em `{tenant}.crm-asfadvogados.test`.

### 6.1 Multi-tenant e autenticação

| Item | Situação |
| --- | --- |
| Domínio central (landing + criar conta) | Entregue (`Welcome`, `onboarding/CreateTenant`) |
| Resolução de tenant por subdomínio | Entregue (`ResolveTenant`, middleware `tenant`) |
| Isolamento por `tenant_id` + Eloquent scope | Entregue (`BelongsToTenant` / `TenantScope`; testes de isolamento) |
| Signup cria tenant + usuário owner | Entregue |
| Fortify (login, registro, e-mail, 2FA, passkeys, settings) | Entregue |
| RBAC completo / RLS Postgres / criptografia de coluna | Pendente |

### 6.2 Módulos de UI habilitados no menu

Menu **Comercial / Operação / Governança** segue o protótipo. Habilitados hoje:

- **Contatos** — listagem, busca, drawer, edição
- **Empresas** — listagem, drawer (contatos + negociações), edição
- **Funil** (antes “Negociações”) — visão quadro e visão lista no mesmo módulo
- **Administração** — CRUD de catálogos de domínio

Ainda desabilitados no menu (“em breve”): *(nenhum — todos os itens do menu lateral estão navegáveis; Painel, Atividades, Calendário, Propostas, Automações, API de tráfego, Site e Compliance usam telas de protótipo com dados ilustrativos).*

### 6.3 Contatos e empresas

**Entregue**

- Listagem Inertia com colunas de UF/município, consentimento (contatos), conflito e valor em negociação (empresas).
- Drawers laterais com campos, consentimentos (contato), contatos vinculados (empresa) e resumo de negociações.
- Edição completa (`/contatos/{id}/edit`, `/empresas/{id}/edit`) com localidade IBGE (UF → município), vínculo contato–empresa, canal e status de consentimento.
- Consentimentos **por finalidade** na edição do contato (`SyncContatoConsentimentos`), com datas de concessão/revogação.
- Na edição do contato: links **Abrir {assunto}** para cada negociação existente.
- Em listas e drawers: botão **Abrir** negociação (quando houver) ou **Nova** (quando não houver), com pré-preenchimento de `contato_id` / `empresa_id`.

**Pendente / parcial**

- Rotas de **criação** e **exclusão** de contato/empresa (hoje a base nasce via seeders/factories).
- Motor de deduplicação (existem apenas campos `registro_mesclado` / `observacao_deduplicacao`).
- Exportação / revogação LGPD nos botões do drawer (UI presente, fluxo operacional não).
- Gate de conflito de interesses na atribuição de responsável.

### 6.4 Funil e negociações

**Entregue**

- Dois funis seedados (B2B consultivo e Concursos/PF) + CRUD admin de funis e etapas (nome, ordem, SLA, campos, cores, exige motivo).
- Board kanban por etapa com **arrastar e soltar** (`PATCH negociacoes/{negociacao}/etapa`), restrito à etapa do mesmo funil.
- Alternância de visão **Funil | Negociações** (lista).
- Abertura de negociação via query `?negociacao={id}` (drawer).
- **Criar negociação** (`/negociacoes/create` + `store`) com funil, etapa, contato, empresa, canal, responsável, valor, previsão e próxima tarefa.
- Botão **Alterar regra**: cicla a string de distribuição do funil ativo entre *round robin por especialidade*, *round robin simples* e *por carga de trabalho* (conforme protótipo), com toast de confirmação.
- Histórico exibido no drawer da negociação (dados seedados / tabela `historicos_negociacao`).

**Pendente / parcial**

- Enforce de campos obrigatórios e motivo de perda ao sair da etapa (dados já existem em `etapas_funil`, mas o move ainda não valida).
- Distribuição **real** (atribuição automática de responsável); hoje só altera o rótulo da regra.
- Append automático de histórico em toda mudança de etapa.
- Motivos de perda como catálogo fechado com relatórios.

### 6.5 Administração de domínio

CRUD Inertia entregue para:

- Finalidades e status de consentimento  
- Setores  
- Canais de contato  
- Status de conflito  
- Tipos de pessoa  
- Funis e etapas do funil  

### 6.6 Localidade (IBGE)

- Catálogo em schema `base_dados_ibge` (`IbgeEstado` / `IbgeMunicipio`).
- `municipio_id` em contatos e empresas, com sync de cidade/UF.
- Endpoint `GET ibge/municipios?estado_id=` para os formulários de edição.

### 6.7 Compliance e LGPD (código)

| Item do desenho | Situação |
| --- | --- |
| Consentimento por finalidade no contato | Entregue (edição + catálogos admin) |
| Texto/versão/IP do opt-in no canal de origem | Pendente |
| Portal / fila de direitos do titular | Pendente (menu Compliance desabilitado) |
| Revisor ético de templates | Pendente |
| Trilha de auditoria append-only com hash | Pendente |
| Separação comercial × caso processual | Mantida por escopo (CRM só comercial); integração processual pendente |

### 6.8 Fora do escopo entregue até agora

Inbox multicanal (WhatsApp, site, Instagram), automações gatilho→condição→ação, tarefas/atividades, calendário, propostas/contratos, painel com métricas reais (o `Dashboard` atual ainda usa dados ilustrativos), indicações, assinatura eletrônica e integração com gestão processual.

### 6.9 Próximos passos sugeridos (alinhados ao MVP)

1. Enforce de saída de etapa (campos obrigatórios + motivo de perda).  
2. Criação de contato/empresa pela UI (além de editar).  
3. Append de histórico ao mover etapa / criar negociação.  
4. Painel alimentado pelo banco (conversão, SLA, motivos).  
5. Distribuição efetiva conforme a regra do funil.  
6. Trilha de auditoria mínima e endurecimento de autorização (além de `auth`/`verified`).

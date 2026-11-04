# Pontos de lapidação do CRM — série de prompts

Série de prompts para implantar os 9 pontos de lapidação levantados pelo cliente (ASF) sobre
o uso diário do CRM: agenda do dia, lembretes de follow-up, pesquisa no funil, telefonia,
cenário do dia, saneamento do funil congestionado, "ATRASADO" em atividades e dashboard ao vivo.

Cada prompt é autocontido — uma sessão de desenvolvimento pega um prompt, implementa
exatamente o que ele descreve contra o repo real, roda os testes que ele pede e segue para o
próximo. **Execute na ordem**: os prompts 02–07 dependem da fundação do 01, e 06/07 dependem
do arquivamento criado no 05.

| Prompt | Itens do cliente | Entrega |
|---|---|---|
| [01_fundacao_datas_tipos_responsavel.md](01_fundacao_datas_tipos_responsavel.md) | base de 1, 2, 5, 8 | Timezone `America/Sao_Paulo`, helper de datas compartilhado no front, `tipo` (follow-up, ligação, reunião, e-mail, tarefa) e `responsavel_user_id` em `tarefas_negociacao`, scopes de "atrasadas/do dia/do responsável". |
| [02_atividades_reais_e_atrasado.md](02_atividades_reais_e_atrasado.md) | 8 | Tela Atividades com dados reais (hoje é mock), rótulo **ATRASADO** vermelho/negrito vs "Hoje", Calendário lendo todas as tarefas. |
| [03_meu_dia_e_lembretes_followup.md](03_meu_dia_e_lembretes_followup.md) | 1, 2 | Página **Hoje** como tela inicial (atrasados, follow-ups do dia, reuniões, demais tarefas), sino de lembretes com badge e selo de follow-up nos cards do funil. |
| [04_cenario_do_dia.md](04_cenario_do_dia.md) | 5 | Visão macro do dia no topo da página Hoje: leads recebidos, follow-ups previstos/atrasados, reuniões, oportunidades por etapa, "exigem atenção". |
| [05_saneamento_funil.md](05_saneamento_funil.md) | 7 | Arquivamento de negociações paradas com motivo obrigatório (perdido, desqualificado, duplicado, sem retorno, sem interesse), tela de Saneamento com sugestões e ação em massa, histórico preservado. |
| [06_pesquisa_no_funil.md](06_pesquisa_no_funil.md) | 3 | Busca no funil por nome, telefone, responsável, etapa e status, com acesso direto à negociação. |
| [07_dashboard_ao_vivo.md](07_dashboard_ao_vivo.md) | 9 | Dashboard com filtros (funil, responsável, período "hoje") e atualização automática por polling do Inertia. |
| [08_avaliacao_telefonia_api4com.md](08_avaliacao_telefonia_api4com.md) | 4, 6 | **Spike de avaliação** da API Api4com (formato de número, autenticação, click-to-call, webhook de registro). Entregável: documento de avaliação + desenho proposto, sem implementação definitiva. |

## Diagnóstico do repo que motivou a série (levantado em 2026-10-07)

- **A tela Atividades é 100% mock.** `resources/js/pages/crm/Atividades.vue` importa
  `LEADS/CONTATOS/EMPRESAS` de `@/data/crm`, tem `USUARIO = 'Camila Moraes'` e
  `const hoje = new Date(2026, 7, 17)` congelado; a rota é `Route::inertia('atividades', ...)`
  sem controller. Isso explica o "atividades e calendário não são confiáveis para conduzir o
  dia". O rótulo "hoje" do item 8 está em `Atividades.vue:53-59`.
- **Único modelo de atividade:** `App\Models\TarefaNegociacao` (tabela `tarefas_negociacao`:
  `descricao`, `data`, `hora`, `status` enum `StatusTarefaNegociacao`, `concluida_em`,
  `criado_por_user_id`). Não tem `tipo` (não dá para distinguir follow-up de reunião) nem
  responsável próprio (só via `negociacoes.responsavel_user_id`).
- **Calendário** (`CalendarioController`) lê apenas `negociacoes.proxima_tarefa_em` e
  `previsao_fechamento` — mostra no máximo uma tarefa por negociação.
- **Timezone UTC fixo** em `config/app.php` (`'timezone' => 'UTC'`, sem `env()`); depois das
  21h o "hoje" do servidor já é amanhã. No front, `NegociacoesEdit.vue` (função
  `abrirNovaTarefa`) usa `new Date().toISOString().slice(0, 10)` — mesmo bug do lado cliente.
  Não existe helper de datas compartilhado (`resources/js/lib/crmFormat.ts` só tem `brl()` e
  `consentStyle()`).
- **Home pós-login** é `/dashboard` (`config/fortify.php` → `'home' => '/dashboard'`),
  `DashboardController` + `App\Actions\Crm\BuildDashboardMetrics` (analítico, só filtro
  `periodo`, sem biblioteca de gráficos, sem polling).
- **Kanban** (`NegociacaoController::index` → `crm/Negociacoes.vue`) carrega todas as
  negociações do tenant sem paginação; filtros só client-side; **sem busca textual**.
  Ganho/perdido vem de `EtapaFunil.resultado` (`EtapaFunilResultado`: aberta/ganho/perdido);
  não há SoftDeletes nem flag de arquivamento; motivo de perda é texto livre
  (`negociacoes.motivo_desqualificacao`). O "funil empresarial" é o funil de slug
  `pj-meta-ads` (seed `MetaAdsPjSeeder`).
- **Notificações inexistentes:** o sino em `CrmHeader.vue` é um botão sem ação; sem tabela
  `notifications`, sem broadcasting (`BROADCAST_CONNECTION=log`).
- **Sem Policies/Gates** e sem noção de "minhas × do time" no backend.
- **Telefone** do contato: coluna única `contatos.telefone`, só dígitos (DDD + número, sem 55),
  via `App\Actions\Crm\NormalizeContatoIdentifiers::telefone()`.

## Decisões já travadas (não reabrir sem motivo novo)

- **Atividade continua sendo `TarefaNegociacao`.** Não criar modelo novo `Atividade`/`FollowUp`:
  ganha as colunas `tipo` e `responsavel_user_id` (prompt 01). Follow-up = tarefa de tipo
  `follow_up`. Reunião = tipo `reuniao`.
- **"Hoje" é calculado no fuso `America/Sao_Paulo`**, no servidor (fonte de verdade) — o front
  só formata. O rótulo vem pronto do backend sempre que possível, para que Atividades,
  Calendário, Hoje e lembretes nunca discordem.
- **Página "Hoje" nova como tela inicial**, em vez de enfiar a agenda no `Dashboard.vue`
  (2300 linhas, analítico). O Dashboard continua sendo o painel de resultado (item 9).
- **Lembretes por prop compartilhada + polling leve**, não por Notifications/broadcast.
  Polling do Inertia (`usePoll`) foi a escolha do usuário também para o dashboard ao vivo —
  sem Reverb/Echo, sem processo extra em produção.
- **Saneamento = arquivamento manual com sugestão**, não rotina automática (escolha do
  usuário). Arquivar **não apaga nem move de etapa**: marca `arquivada_em` + motivo
  obrigatório, preserva dados/histórico e sai do funil ativo. Reversível (desarquivar).
- **Telefonia: só avaliação técnica nesta série** (escolha do usuário). Nada de click-to-call
  em produção até o documento do prompt 08 dar "go".

## Reuso obrigatório (não reinventar)

- `App\Models\TarefaNegociacao`, `App\Enums\StatusTarefaNegociacao` (`isAberta()`, `abertos()`).
- `App\Http\Controllers\NegociacaoTarefaController` + `StoreTarefaNegociacaoRequest`/
  `UpdateTarefaNegociacaoRequest` — CRUD de tarefa já existente (rotas
  `negociacoes.tarefas.store|update|destroy`).
- `App\Actions\Crm\SyncProximaTarefaNegociacao` — mantém `negociacoes.proxima_tarefa*`
  sincronizado; chamar sempre que tarefa mudar de status/data.
- `App\Actions\Crm\RegistrarHistoricoNegociacao` — linha do tempo em `historicos_negociacao`.
- `App\Http\Resources\NegociacaoResource` — shape dos cards do kanban.
- `CrmNegociacaoDrawer` + query `?negociacao=ID` em `negociacoes.index` — acesso direto à
  oportunidade (usar em todo link "abrir negociação").
- `App\Actions\Crm\BuildDashboardMetrics` — métricas do dashboard.
- `App\Models\EtapaFunil` (`resultado`, `sla`, `isGanho()`, `isPerdido()`).
- `App\Actions\Crm\NormalizeContatoIdentifiers` — normalização de telefone.
- `App\Http\Middleware\HandleInertiaRequests::share` — props compartilhadas (`crmCounts` já
  existe ali, é o modelo para `lembretes`).
- `App\Support\Tenancy\CurrentTenant` / `App\Models\Concerns\BelongsToTenant` — todo modelo
  novo é tenant-scoped.
- Testes: `tests/TestCase.php` (cria `$this->tenant`), `tests/Concerns/InteractsWithTenants.php`
  (`tenantUrl`, `asTenant`), factories `TarefaNegociacaoFactory`, `NegociacaoFactory`,
  `FunilFactory`, `EtapaFunilFactory`, `ContatoFactory`, `UserFactory`.
- Menu lateral: item novo exige `resources/js/data/crm.ts` (`ScreenKey`, `NAV_GROUPS`,
  `SCREEN_TITLES`), `resources/js/lib/crmNav.ts` (`PAGE_SCREEN`, `ENABLED_SCREENS`,
  `screenHref()`), `resources/js/components/crm/CrmNavIcon.vue` (ícone) e rota em
  `routes/web.php` no grupo `auth` + `verified`.

## Convenções e gotchas do repo (herdados de séries anteriores)

- Testes são **classes PHPUnit** (`class XTest extends TestCase`, `use RefreshDatabase`,
  métodos `test_*`), apesar do Pest instalado — seguir o estilo existente. Asserção típica:
  `$this->actingAs($user)->withoutVite()->get($this->tenantUrl('rota', [...]))->assertInertia(fn (Assert $page) => ...)`.
- Banco de teste é sqlite em memória: nada de SQL específico de MySQL nas queries novas
  (usar Query Builder/`whereDate`, evitar `DATE_SUB`, `IF()`, etc.).
- Foreign key para negociações: sempre `->constrained('negociacoes')` explícito (o Laravel
  pluraliza errado para `negociacaos`). Idem `tarefas_negociacao`.
- Commands usam atributos `#[Signature]`/`#[Description]`; Jobs usam
  `Illuminate\Foundation\Queue\Queueable`.
- Rotas novas: rodar `php artisan wayfinder:generate` antes de importar de `@/routes/...` ou
  `@/actions/...` no `.vue`.
- Datas brasileiras: `CarbonImmutable::createFromFormat('d/m/Y', ...)`, nunca `parse()` em
  `dd/mm/aaaa`.
- Critério de pronto de todo prompt: `php artisan test` verde (falhas pré-existentes não
  relacionadas devem ser apontadas, não "consertadas" no escopo), `vendor/bin/phpstan` sem erro
  novo, `npx vue-tsc --noEmit` sem erro novo, `npm run build` ok, `vendor/bin/pint --dirty`.

## Estado da implementação

Série **escrita em 2026-10-07, não executada.** Ao executar cada prompt, registrar aqui os
desvios do desenho descobertos na implementação (mesmo padrão de
`documents/automacao_email_gmail/README.md`).

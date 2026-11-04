# Prompt 01 — Fundação: fuso horário, datas compartilhadas, tipo e responsável da tarefa

> Leia `README.md` desta pasta antes. Este prompt não entrega tela nova: ele prepara o modelo
> de dados e as regras de data que os prompts 02–07 usam. Sem ele, "hoje", "atrasado" e
> "follow-up" não têm definição confiável.

## Objetivo

1. O CRM passa a calcular "hoje" no fuso de Brasília, no servidor e no cliente.
2. Toda tarefa (`TarefaNegociacao`) passa a ter **tipo** (follow-up, ligação, reunião, e-mail,
   tarefa) e **responsável** próprio.
3. Existem scopes reutilizáveis para "pendentes", "atrasadas", "do dia" e "do responsável", e
   um único lugar que decide o rótulo de prazo ("ATRASADO" / "Hoje" / "Amanhã" / "em N dias").

## Contexto do repo (verificado)

- `config/app.php`: `'timezone' => 'UTC'` fixo (sem `env()`).
- `app/Models/TarefaNegociacao.php` — tabela `tarefas_negociacao` (migration
  `2026_08_26_133021_create_tarefas_negociacao_table.php` + `concluida_em` em
  `2026_09_09_170346_...`): `tenant_id`, `negociacao_id`, `descricao`, `data` (date), `hora`
  (time null), `status` (`StatusTarefaNegociacao`: pendente, em_andamento, concluida,
  cancelada), `concluida_em`, `criado_por_user_id`. Casts: `data => date`,
  `hora => 'datetime:H:i'`.
- `app/Enums/StatusTarefaNegociacao.php` — `label()`, `isAberta()`, `abertos()`.
- `app/Http/Controllers/NegociacaoTarefaController.php` (store/update/destroy, redireciona para
  `negociacoes.edit`) + `StoreTarefaNegociacaoRequest`/`UpdateTarefaNegociacaoRequest`.
- `app/Actions/Crm/SyncProximaTarefaNegociacao.php` — copia a tarefa aberta mais próxima para
  `negociacoes.proxima_tarefa`, `proxima_tarefa_em`, `proxima_tarefa_hora`.
- `resources/js/pages/crm/NegociacoesEdit.vue` — único formulário real de tarefa. Função
  `abrirNovaTarefa()` usa `new Date().toISOString().slice(0, 10)` (data UTC — vira o dia
  seguinte após 21h). `formatarData(iso)` local.
- `NegociacaoController::edit` monta o array de tarefas para a página (`id, descricao, data,
  hora, status, statusLabel, concluidaEm`).
- `resources/js/lib/crmFormat.ts` — só `brl()` e `consentStyle()`; cada página tem seus
  próprios helpers de data (`Calendario.vue`: `parseIsoDate`, `sameDay`).
- `database/factories/TarefaNegociacaoFactory.php` — padrão `data = now()->toDateString()`,
  `hora '09:00'`, `Pendente`.

## Passos

### 1. Fuso horário
- `config/app.php`: `'timezone' => env('APP_TIMEZONE', 'America/Sao_Paulo')`. Adicionar
  `APP_TIMEZONE=America/Sao_Paulo` em `.env.example`.
- Verificar impacto em `created_at`/timestamps já gravados em UTC: o Laravel grava no fuso da
  app, então dados antigos ficam 3h "adiantados" na leitura. **Não** fazer migração de dados;
  documentar no README desta pasta (seção Estado) e confirmar que nenhum teste existente
  depende de UTC (rodar a suíte inteira antes/depois). Conferir agendamentos em
  `routes/console.php` (Meta/leads) — horários fixos (`dailyAt`) passam a ser em horário de
  Brasília; ajustar se algum dependia de UTC.
- Se o MySQL de produção usa `time_zone` próprio, registrar no README (não alterar banco).

### 2. Colunas novas em `tarefas_negociacao`
Migration nova:
- `tipo` string(20) default `'tarefa'`, indexada — enum novo `App\Enums\TipoTarefaNegociacao`
  (string-backed): `FollowUp = 'follow_up'`, `Ligacao = 'ligacao'`, `Reuniao = 'reuniao'`,
  `Email = 'email'`, `Tarefa = 'tarefa'`, com `label()` ("Follow-up", "Ligação", "Reunião",
  "E-mail", "Tarefa") e `cor()` (token para o front; manter as cores já usadas no mock de
  `Atividades.vue`: ligação `#3F5E8C`, reunião `#8C4A6B`, e-mail `var(--primary)`, tarefa
  `#5F6E52`; follow-up escolher uma cor de destaque coerente com o tema).
- `responsavel_user_id` foreignId nullable `->constrained('users')->nullOnDelete()`, indexado.
- Índice composto `(tenant_id, status, data)` para as consultas do dia.
- **Backfill** na própria migration: `responsavel_user_id = negociacoes.responsavel_user_id`
  (update com subquery compatível com sqlite e MySQL). Tarefas existentes ficam `tipo =
  'tarefa'` — exceto se a descrição começar com "follow" (case-insensitive) → `follow_up`
  (heurística simples, documentar).

### 3. Model `TarefaNegociacao`
- Cast `tipo => TipoTarefaNegociacao::class`; relação `responsavel(): BelongsTo` (User).
- Scopes (com tipagem `Builder`):
  - `abertas()` → status em `StatusTarefaNegociacao::abertos()`.
  - `atrasadas(?CarbonInterface $hoje = null)` → abertas com `data < hoje`.
  - `doDia(?CarbonInterface $dia = null)` → abertas com `data = dia`.
  - `doResponsavel(User|int $user)` → `responsavel_user_id = id`.
  - `followUps()` → `tipo = follow_up`.
  - Negociações arquivadas ainda não existem — o prompt 05 acrescenta o filtro
    `whereHas('negociacao', fn ($q) => $q->ativas())` a esses scopes.
- Método `prazoSituacao(?CarbonInterface $hoje = null): string` retornando
  `'atrasado' | 'hoje' | 'futuro' | 'concluido'` — **regra única** do sistema:
  - `concluido` se status não é aberto (concluída ou cancelada) — nunca "ATRASADO".
  - `atrasado` se aberta e `data < hoje` (por **data**; a hora não torna atrasado no mesmo dia).
  - `hoje` se aberta e `data == hoje`.
  - `futuro` caso contrário.
- Método `prazoRotulo(): string` → `'ATRASADO'`, `'Hoje'`, `'Amanhã'`, `'em N dias'`,
  `'Concluída'`/`'Cancelada'`.

### 4. Criação/edição de tarefa
- `StoreTarefaNegociacaoRequest`/`UpdateTarefaNegociacaoRequest`: `tipo` obrigatório
  (`Rule::enum(TipoTarefaNegociacao::class)`), `responsavel_user_id` nullable `exists` restrito
  a usuários do tenant atual.
- `NegociacaoTarefaController::store`: se `responsavel_user_id` não vier, usar o responsável da
  negociação; se a negociação não tiver, o usuário logado.
- Ao marcar `concluida`, manter o comportamento atual de `concluida_em` +
  `SyncProximaTarefaNegociacao`.
- `NegociacaoController::edit`: incluir `tipo`, `tipoLabel`, `responsavel` (`{id, name}`),
  `prazoSituacao`, `prazoRotulo` no array de tarefas; passar lista de usuários do tenant para
  o select.
- `NegociacoesEdit.vue`: select de Tipo (default `follow_up` quando a ação vem do botão de
  follow-up, senão `tarefa`), select de Responsável, e corrigir `abrirNovaTarefa()` para usar a
  data local (helper do passo 5).
- `HistoricoNegociacao` (via `RegistrarHistoricoNegociacao::tarefaCriada`): incluir o tipo no
  título ("Follow-up agendado para 08/10").

### 5. Helper de datas no front
Criar `resources/js/lib/crmDatas.ts` (funções puras, sem dependência nova):
- `hojeIso(): string` — data local `YYYY-MM-DD` (sem `toISOString`).
- `parseIsoDate(iso): Date` — meia-noite local (mover a versão do `Calendario.vue` para cá).
- `formatarDataBr(iso): string` → `dd/mm/aaaa`; `formatarDiaMes(iso)` → `dd/mm`.
- `diffDias(isoA, isoB): number` — por data de calendário (não por `/86400000` arredondado).
- Tipos `PrazoSituacao = 'atrasado' | 'hoje' | 'futuro' | 'concluido'`.
- Componente `resources/js/components/crm/PrazoBadge.vue` recebendo `situacao` + `rotulo`
  (vindos do backend) e aplicando o estilo. O estilo **ATRASADO** é definido aqui (usado no 02):
  maiúsculas, `font-bold`, `text-destructive` (vermelho do tema; validar contraste no dark).
  "Hoje" em `text-primary`, peso normal.
- Trocar os helpers duplicados de `Calendario.vue` e `NegociacoesEdit.vue` pelos do lib.

### 6. Factory
`TarefaNegociacaoFactory`: `tipo` default `tarefa`, states `followUp()`, `reuniao()`,
`ligacao()`, `atrasada(int $dias = 1)`, `paraHoje()`; `responsavel_user_id` herdado da
negociação no `afterMaking` quando nulo.

## Fora de escopo
Tela de Atividades (02), página Hoje (03), arquivamento (05). Não criar Policies.

## Testes (classes PHPUnit em `tests/Feature` / `tests/Unit`)
- `tests/Unit/TarefaNegociacaoPrazoTest.php`: tabela de casos de `prazoSituacao`/`prazoRotulo`
  com `Carbon::setTestNow()` — ontem pendente → atrasado/"ATRASADO"; hoje → hoje/"Hoje";
  amanhã → "Amanhã"; ontem concluída → concluido; ontem cancelada → concluido; **23h30 de
  Brasília** com tarefa de hoje continua "Hoje" (prova do fuso).
- `tests/Feature/NegociacaoTarefaTest.php` (estender): store com `tipo` e responsável; default
  do responsável; `tipo` inválido → 422; responsável de outro tenant → 422.
- Teste da migration/backfill: tarefa pré-existente recebe responsável da negociação.
- Scopes `atrasadas`/`doDia`/`doResponsavel` isolados por tenant.

## Pronto quando
Suíte verde, phpstan/vue-tsc sem erro novo, `npm run build` ok, criar uma tarefa pela tela de
edição da negociação após 21h mostra a data de hoje (não amanhã), e o README desta pasta
registra o impacto do fuso nos timestamps antigos.

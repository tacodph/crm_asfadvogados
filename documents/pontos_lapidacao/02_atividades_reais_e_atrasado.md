# Prompt 02 — Atividades e Calendário com dados reais + indicador "ATRASADO"

> Pré-requisito: prompt 01 executado (fuso `America/Sao_Paulo`, `tipo`/`responsavel_user_id`
> em `tarefas_negociacao`, `prazoSituacao()`/`prazoRotulo()`, `PrazoBadge.vue`,
> `resources/js/lib/crmDatas.ts`).

## Objetivo (itens 8 do cliente)

"Atividades e calendário não são confiáveis para conduzir o dia." Causa real: a tela
Atividades é um protótipo com dados falsos e o Calendário só enxerga uma tarefa por
negociação. Ao fim deste prompt:

- **Atividades** lista as tarefas reais do tenant (`tarefas_negociacao`), com filtros e KPIs
  calculados no servidor.
- Onde hoje aparece "hoje" para uma atividade vencida, passa a aparecer **ATRASADO**:
  - em letras maiúsculas;
  - em negrito;
  - na cor vermelha (`text-destructive`);
  - **apenas** para atividades pendentes (abertas) com data prevista anterior a hoje.
- Atividades com vencimento hoje continuam com **"Hoje"**.
- **Calendário** mostra todas as tarefas abertas (não só a próxima de cada negociação), com o
  mesmo rótulo.

## Contexto do repo (verificado)

- `routes/web.php`: `Route::inertia('atividades', 'crm/Atividades')->name('atividades.index')`
  (sem controller).
- `resources/js/pages/crm/Atividades.vue`: mock completo — `import { CONTATOS, EMPRESAS, LEADS }
  from '@/data/crm'`, `USUARIO = 'Camila Moraes'`, `TIPOS` fake, `const hoje = new Date(2026, 7,
  17)`, rótulo relativo nas linhas ~53-59 (`dias === 0 ? 'hoje' : ...`), conclusão só local
  (`reactive feitas`). Layout (KPIs, chips de filtro Atuais/Atrasadas/Concluídas/Todas, toggle
  Minhas/Do time, tabela Atividade/Prazo/Negociação/Cliente/Responsável/Status) está bom e
  deve ser **mantido visualmente**.
- `resources/js/data/crm.ts` → `SCREEN_TITLES.atividades` tem subtítulo hardcoded "tarefas
  atribuídas a Camila Moraes".
- `app/Http/Controllers/CalendarioController.php` — lê `negociacoes.proxima_tarefa_em` e
  `previsao_fechamento`; passa `'hoje' => now()->toDateString()`.
- `resources/js/pages/crm/Calendario.vue` — `statusVisual()` (~linhas 83-97) retorna
  `tarefa_hoje`/`tarefa_atrasada`; rótulo `'Tarefa · hoje'` (~59); legenda "Tarefa hoje".
- Testes existentes: `tests/Feature/CalendarioTest.php`, `CrmPagesTest.php`,
  `CrmPrototypePagesTest.php` (pode afirmar que Atividades é protótipo — ajustar).

## Passos

### 1. Backend de Atividades
- `App\Http\Controllers\AtividadeController@index(Request)` e trocar a rota para
  `Route::get('atividades', [AtividadeController::class, 'index'])->name('atividades.index')`
  (mesmo nome — não quebra links do menu).
- Query params validados: `filtro` ∈ `atuais|atrasadas|hoje|concluidas|todas` (default
  `atuais`), `escopo` ∈ `minhas|time` (default `minhas`), `tipo` opcional (enum), `responsavel`
  opcional (só faz sentido em `time`).
- Query base: `TarefaNegociacao::query()->with(['negociacao.contato', 'negociacao.empresa',
  'responsavel'])`; `minhas` = `doResponsavel(auth()->user())`.
  - `atuais` = abertas; `atrasadas` = `atrasadas()`; `hoje` = `doDia()`; `concluidas` = status
    concluída; `todas` = sem filtro (limitar últimos 90 dias de concluídas para não explodir).
  - Ordenação: atrasadas primeiro (data asc), depois hoje por hora, depois futuras.
  - Paginação simples (`paginate(50)->withQueryString()`).
- KPIs (sempre do escopo selecionado, independentes do filtro): Pendentes, **Em atraso**, Para
  hoje, Concluídas (hoje).
- Shape de cada linha (Resource `AtividadeResource`): `id`, `descricao`, `tipo`, `tipoLabel`,
  `tipoCor`, `data` (ISO), `hora`, `prazoTexto` (`dd/mm · HH:MM`), `prazoSituacao`,
  `prazoRotulo`, `status`, `statusLabel`, `negociacao: {id, assunto, funilId}`, `cliente`
  (empresa ou contato), `responsavel: {id, name}`, `urlNegociacao`
  (`negociacoes.index?negociacao=ID`).

### 2. Concluir/reabrir da própria lista
- Reutilizar `PATCH negociacoes.tarefas.update` com `status` — hoje ele redireciona para
  `negociacoes.edit`. Ajustar para `return back()` quando a requisição vier com
  `?origem=atividades` (ou redirecionar para `url()->previous()` de forma geral, se não quebrar
  o fluxo da tela de edição — conferir `NegociacaoTarefaTest`).
- Front: `router.patch(..., { status: 'concluida' }, { preserveScroll: true, only: ['atividades',
  'kpis'] })` com update otimista do checkbox.

### 3. Front de Atividades
- `Atividades.vue`: remover imports de `@/data/crm` (dados), `USUARIO`, `TIPOS`, `hoje` fixo e
  `feitas` reativo. Props tipadas vindas do controller; filtros e escopo via
  `router.get(atividades.index.url({ query }), {}, { preserveState: true, preserveScroll: true,
  replace: true })`.
- Coluna Prazo: data/hora + `<PrazoBadge :situacao :rotulo>`. **Regra visual**:
  - `atrasado` → `ATRASADO` (uppercase, bold, vermelho). Remover o antigo "Nd em atraso" do
    rótulo principal; se quiser manter a informação, mostrar "há N dias" em cinza **abaixo**,
    nunca substituindo "ATRASADO".
  - `hoje` → `Hoje` (cor primária, peso normal).
  - `futuro` → "Amanhã"/"em N dias".
  - `concluido` → "Concluída" em cinza (nunca vermelho, mesmo se vencida).
- Coluna Status: mesmo critério (badge "Atrasada" só para aberta vencida).
- Linha clicável → `urlNegociacao` (abre o drawer da negociação).
- Filtro de tipo (chips: Todos, Follow-up, Ligação, Reunião, E-mail, Tarefa).
- `SCREEN_TITLES.atividades`: subtítulo genérico ("suas tarefas e follow-ups") ou dinâmico com
  o nome do usuário logado — sem nome hardcoded.

### 4. Calendário
- `CalendarioController`: passar a ler `TarefaNegociacao` abertas (e concluídas do mês, para
  histórico) no intervalo do mês exibido, com `tipo`, `prazoSituacao`, `prazoRotulo`,
  responsável e link da negociação; manter os eventos de `previsao_fechamento` como já são.
  `'hoje' => today()->toDateString()` (já no fuso do prompt 01).
- Filtro Minhas/Do time igual ao de Atividades.
- `Calendario.vue`: usar `crmDatas.ts`; o chip do evento atrasado mostra **ATRASADO** com o
  mesmo `PrazoBadge`; "Tarefa · hoje" vira "Hoje"; legenda ganha "ATRASADO" em vermelho.
  Cor do evento por `tipo` (reunião destacada).

### 5. Limpeza
- Se `LEADS`/campos `tarefa*` de `@/data/crm` ficarem sem uso após a troca, remover só o que
  for exclusivo de Atividades (não mexer no que `Site.vue`/`Automacoes.vue` ainda usam).

## Fora de escopo
Página Hoje (03), lembretes (03), arquivamento (05).

## Testes
- `tests/Feature/AtividadesTest.php`:
  - lista só tarefas do tenant (isolamento);
  - `minhas` × `time`;
  - filtro `atrasadas` traz pendente de ontem e **não** traz concluída de ontem nem pendente de
    hoje;
  - prop `prazoRotulo` = `'ATRASADO'` para pendente de ontem e `'Hoje'` para pendente de hoje;
    concluída vencida → `'Concluída'`;
  - KPIs batem com as factories;
  - concluir pela lista volta para Atividades e atualiza `negociacoes.proxima_tarefa`.
- `CalendarioTest`: duas tarefas abertas na mesma negociação aparecem ambas; atrasada vem com
  `prazoSituacao = atrasado`.
- Ajustar `CrmPrototypePagesTest`/`CrmPagesTest` se afirmarem que Atividades é protótipo.

## Pronto quando
Suíte verde, phpstan/vue-tsc ok, `npm run build` ok, e manualmente: criar uma tarefa com data
de ontem e outra com data de hoje numa negociação → Atividades mostra **ATRASADO** (vermelho,
negrito, maiúsculo) na primeira e "Hoje" na segunda; concluir a primeira remove o ATRASADO.

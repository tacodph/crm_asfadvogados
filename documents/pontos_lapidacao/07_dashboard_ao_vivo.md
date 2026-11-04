# Prompt 07 — Dashboard ao vivo do resultado, com filtros

> Pré-requisitos: prompts 01–05 (fuso correto; arquivadas fora do "em aberto").
> Decisão do usuário: **polling do Inertia (`usePoll`)**, sem WebSocket/Reverb.

## Objetivo (item 9 do cliente)

"Criar DASHBOARD AO VIVO do resultado c/ filtro." O painel de resultado deve se atualizar
sozinho enquanto está aberto (ex.: numa TV da equipe comercial) e permitir filtrar.

## Contexto do repo (verificado)

- `DashboardController@index` lê `?periodo=` (default `'30 dias'`) →
  `App\Actions\Crm\BuildDashboardMetrics` (~980 linhas) → `Inertia::render('Dashboard', ...)`.
  Props: `periodo`, `periodos` (`['7 dias','30 dias','trimestre']`), `kpis`, `conversao`,
  `conversaoFunis`, `conversaoNegociacoes`, `canais`, `perdas`, `equipe`,
  `atendimentosEncerrados`, `perfilLeadsMeta`.
- `resources/js/pages/Dashboard.vue` (~2300 linhas), gráficos desenhados à mão (sem lib).
  Filtro de período via `router.get(dashboard.url({query:{periodo}}), {}, {preserveState,
  preserveScroll})`. `CrmLayout.vue` sincroniza `periodo` via `provide('crmPeriodo')` e evento
  `crm:periodo`.
- Sem filtro por funil nem responsável. Sem polling.
- `tests/Feature/DashboardTest.php`.

## Passos

### 1. Filtros no backend
- `DashboardController` valida (FormRequest ou `$request->validate`): `periodo` ∈
  `hoje|7 dias|30 dias|trimestre|mes_atual` (acrescentar **hoje** e **mês atual**), `funil`
  (id do tenant, opcional), `responsavel` (user id do tenant, opcional).
- `BuildDashboardMetrics`: receber um DTO `FiltrosDashboard { periodo, inicio, fim, funilId,
  responsavelId }` e aplicar funil/responsável na **query base** de negociações (centralizar
  num método `baseNegociacoes()` em vez de espalhar `where` por cada seção — refatorar o
  mínimo necessário). Seções que não fazem sentido com filtro (ex.: `perfilLeadsMeta` com
  responsável) continuam funcionando e mostram nota "filtro não se aplica" se preciso.
- Excluir arquivadas de "em aberto"; manter o que o prompt 05 definiu (arquivadas como
  saída por motivo).
- **Cache curto**: `Cache::remember("dashboard:{tenant}:{hash(filtros)}", 30, ...)` para
  que várias telas abertas com poll não recalculem tudo a cada ciclo. Chave por tenant
  obrigatória (isolamento). Não cachear quando `app()->runningUnitTests()` atrapalhar os
  testes — preferir `Cache::flush()` no setUp ou driver `array`.
- Prop nova `atualizadoEm` (ISO, hora de geração dos dados).

### 2. KPIs "ao vivo" do dia
Acrescentar um bloco de topo "Hoje" (independente do período escolhido, mas respeitando
funil/responsável): leads recebidos hoje, negociações ganhas hoje (valor), perdidas/arquivadas
hoje, follow-ups concluídos × previstos hoje, reuniões hoje. Reaproveitar
`BuildCenarioDoDia` (prompt 04) onde as métricas coincidirem — **não duplicar a lógica**.

### 3. Front
- `Dashboard.vue`: barra de filtros (Período, Funil, Responsável) sincronizada com a URL
  (query string — dá para deixar a TV com um link fixo). Manter a integração com
  `crmPeriodo` do layout.
- Polling: `usePoll(60_000, { only: [lista de props de métricas + 'atualizadoEm'] })` —
  partial reload, preservando filtros/scroll. Indicador no canto: ponto verde "Ao vivo ·
  atualizado há 12 s" (contador local a partir de `atualizadoEm`), botão **Pausar/Retomar**
  (`stop()`/`start()` do usePoll) e botão "Atualizar agora". Com a aba oculta o poll pausa
  (default `keepAlive: false`).
- Modo apresentação (opcional, barato): query `?tv=1` esconde sidebar/header e aumenta
  fontes dos KPIs.
- Destacar variações entre dois polls (ex.: KPI que mudou pisca 1 s) — só CSS, sem lib.

## Fora de escopo
WebSocket/broadcast. Biblioteca de gráficos nova. Exportação.

## Testes (`tests/Feature/DashboardTest.php`, estender)
- filtro `funil` restringe KPIs/conversão às negociações do funil;
- filtro `responsavel` idem;
- `periodo=hoje` conta só o dia (com `Carbon::setTestNow`, fuso Brasília);
- `funil`/`responsavel` de outro tenant → 422 (ou ignorado com 404 — decidir e testar);
- arquivada não conta como em aberto;
- partial reload (`X-Inertia-Partial-Data`) devolve só as props pedidas + `atualizadoEm`;
- cache isolado por tenant (dois tenants, mesmos filtros, números diferentes).

## Pronto quando
Suíte verde, phpstan/vue-tsc ok, build ok; manualmente: abrir o Dashboard filtrado por um
funil, criar uma negociação nesse funil em outra aba → em até ~60–90 s (poll + cache) o KPI
"Leads hoje" sobe sem recarregar a página.

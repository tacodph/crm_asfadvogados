# Prompt 03 — Página "Hoje" (tela inicial) e lembretes de follow-up

> Pré-requisitos: prompts 01 e 02 (tipo/responsável nas tarefas, regra única de prazo,
> `PrazoBadge.vue`, `AtividadeResource`).

## Objetivo (itens 1 e 2 do cliente)

**Item 1 — Notificações e atividades do dia.** Ao abrir o CRM, o usuário cai numa tela que
mostra imediatamente:
- follow-ups pendentes (atrasados);
- follow-ups do dia;
- atividades atrasadas;
- reuniões e compromissos;
- demais tarefas programadas.

**Item 2 — Lembretes de follow-ups.** Mecanismo visual claro de quais contatos precisam ser
retomados **agora**, com acesso direto à oportunidade, para nenhuma oportunidade ficar sem
retorno.

## Contexto do repo (verificado)

- `config/fortify.php`: `'home' => '/dashboard'`. `/` é a landing pública (`Welcome`), não
  mexer nela.
- `resources/js/app.ts` decide layout: `Dashboard`, `crm/*`, `settings/*` → `CrmLayout`.
- `resources/js/components/crm/CrmHeader.vue`: sino é
  `<button type="button" class="topbar-icon-btn" title="Notificações"><Bell /></button>` sem
  ação.
- `app/Http/Middleware/HandleInertiaRequests.php::share` já compartilha `auth.user`, `tenant`,
  `crmCounts` — modelo para a prop nova.
- Acesso direto à oportunidade: `negociacoes.index?negociacao=ID` abre `CrmNegociacaoDrawer`.
- `App\Http\Resources\NegociacaoResource` — shape dos cards do kanban (`slaLabel`,
  `quandoRelativo()` etc.).
- Menu: `resources/js/data/crm.ts`, `resources/js/lib/crmNav.ts`, `CrmNavIcon.vue`.
- Inertia v3 (`@inertiajs/vue3`): `usePoll`, partial reload `only`, `Inertia::defer`/closures
  lazy em props.

## Passos

### 1. Página Hoje
- Rota `GET hoje` → `App\Http\Controllers\HojeController@index` → `Inertia::render('crm/Hoje')`,
  nome `hoje.index`, no grupo `auth` + `verified`.
- `config/fortify.php`: `'home' => '/hoje'`. Conferir testes de login/registro que afirmam
  redirect para `/dashboard` (`tests/Feature/Auth/*`) e atualizar.
- Menu lateral: item **Hoje** como primeiro do grupo Comercial (ícone sol/calendário-check em
  `CrmNavIcon.vue`), com contador = atrasados + follow-ups do dia do usuário.
- Escopo padrão **Minhas** (responsável = usuário logado); toggle **Do time** para
  gerente/owner/admin (`auth.user.role` ∈ owner/admin/gerente — checar slugs em
  `App\Models\Role`).
- Seções (cada uma com contagem no título, vazia mostra "Nada por aqui"):
  1. **Follow-ups atrasados** — `followUps()->atrasadas()` (destaque máximo, borda vermelha,
     `PrazoBadge` ATRASADO).
  2. **Follow-ups de hoje** — `followUps()->doDia()`, ordenados por hora; os com hora já
     passada ganham marca "agora" (ex.: ponto pulsante).
  3. **Outras atividades atrasadas** — `atrasadas()` com tipo ≠ follow-up.
  4. **Reuniões e compromissos de hoje** — `tipo = reuniao` `doDia()` (e ligações agendadas
     com hora).
  5. **Demais tarefas de hoje** — restante de `doDia()`.
  6. **Próximos dias** (colapsado) — abertas nos próximos 3 dias.
- Cada item: tipo, descrição, hora, contato/empresa, telefone do contato (link `tel:` e
  WhatsApp `https://wa.me/55<digitos>` — telefone já é só dígitos), etapa/funil, e ações:
  **Abrir negociação** (drawer), **Concluir** (PATCH do prompt 02, `only` parcial),
  **Reagendar** (popover com +1 dia / +3 dias / data livre → PATCH `data`).
- Reaproveitar `AtividadeResource` do prompt 02 (acrescentar `contatoTelefone`, `etapa`,
  `funil` se faltar). Uma única query por seção, eager loading, sem N+1.
- O topo da página receberá o "Cenário do dia" no prompt 04: estruturar o componente para
  aceitar um bloco acima das seções (sem placeholder visível agora).

### 2. Lembretes (sino)
- Prop compartilhada **lazy** `lembretes` em `HandleInertiaRequests::share` (closure, só
  resolve se a página/partial pedir — não pesar todas as requisições): para o usuário logado,
  `{ total, atrasados, agora, itens: [até 10 follow-ups mais urgentes] }`, onde:
  - `atrasados` = follow-ups abertos com data < hoje;
  - `agora` = follow-ups de hoje com `hora <= now()` ou sem hora;
  - itens com `id`, `descricao`, `contatoNome`, `prazoRotulo`, `prazoSituacao`, `urlNegociacao`.
  - Não autenticado / sem tenant → `null`.
- `CrmHeader.vue`: sino vira dropdown (reka-ui `DropdownMenu`/`Popover`, já é dependência)
  com **badge numérico vermelho** quando `total > 0`; lista os itens com `PrazoBadge` e link
  direto para a negociação; rodapé "Ver todos na página Hoje".
- Atualização: `usePoll(120_000, { only: ['lembretes'] }, { keepAlive: false })` no layout
  (`CrmLayout.vue`) — pausa com aba oculta (comportamento padrão do usePoll); garantir que o
  poll não reseta estado de formulários abertos (partial reload só de `lembretes`).
- Ao entrar no CRM pela primeira vez no dia (flag em `sessionStorage` com try/catch), se
  `atrasados > 0` mostrar um toast (`vue-sonner`, já instalado): "Você tem N follow-ups
  atrasados" com ação "Ver".

### 3. Indicador no funil (kanban)
- `NegociacaoResource`: campo `followUp` = `{ situacao: 'atrasado'|'hoje'|null, rotulo }`
  calculado a partir do follow-up aberto mais próximo da negociação (eager load
  `tarefas` filtradas por tipo/abertas no `NegociacaoController::index` para não gerar N+1 —
  ou usar `proxima_tarefa_em` + um `withExists`/subselect do tipo; escolher a opção sem N+1 e
  documentar).
- `Negociacoes.vue` (card e modo lista): selo pequeno "Follow-up ATRASADO" (vermelho) ou
  "Follow-up hoje" (primária). Filtro rápido "Precisa de retorno" no topo do kanban que mostra
  só cards com selo.

## Fora de escopo
Métricas macro (prompt 04). Notificações por e-mail/push/WhatsApp. Broadcast em tempo real.

## Testes
- `tests/Feature/HojePageTest.php`: seções corretas com factories (follow-up ontem, follow-up
  hoje, reunião hoje, tarefa amanhã, follow-up concluído ontem — este não aparece); escopo
  minhas × time; vendedor não consegue `escopo=time` (cai em minhas); isolamento de tenant.
- Login redireciona para `/hoje`.
- `tests/Feature/LembretesFollowUpTest.php`: prop `lembretes` via partial reload
  (`X-Inertia-Partial-Data: lembretes`) com contagens certas; não aparece para outro usuário;
  limite de 10 itens ordenados por urgência.
- `NegociacaoTest`/kanban: `followUp.situacao` correto; teste que conta queries
  (`DB::enableQueryLog`) para provar ausência de N+1 com 20 negociações.

## Pronto quando
Suíte verde, phpstan/vue-tsc ok, build ok; manualmente: logar → cai em **Hoje** com as seções;
sino mostra badge com os follow-ups atrasados e leva direto à negociação; card do kanban exibe
"Follow-up ATRASADO".

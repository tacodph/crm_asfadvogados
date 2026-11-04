# Prompt 05 — Saneamento do funil: arquivar leads parados com motivo

> Pré-requisitos: prompts 01–04. Decisão do usuário: **manual + sugestão** — o sistema
> sugere quem está parado, uma pessoa confirma e escolhe o motivo. Sem rotina automática.

## Objetivo (item 7 do cliente)

"O funil empresarial está congestionado." Remover do **funil ativo** os leads parados,
vencidos ou sem possibilidade de continuidade comercial. Antes da remoção, cada lead deve ser
classificado como:
- perdido;
- desqualificado;
- duplicado;
- sem retorno;
- sem interesse.

Os leads retirados **permanecem armazenados** no histórico do CRM, com dados e registros
preservados, para consultas e relatórios futuros.

## Contexto do repo (verificado)

- `App\Models\Negociacao` (`negociacoes`), observer `App\Observers\NegociacaoObserver`.
  Sem SoftDeletes. Sem flag de arquivamento. Ganho/perdido = etapa com
  `EtapaFunil.resultado` (`EtapaFunilResultado`). Motivo de perda hoje = texto livre
  `motivo_desqualificacao`.
- Actions relacionadas em `app/Actions/Crm/`: `SyncConclusaoNegociacao`,
  `MoverNegociacoesEncerradasParaEtapa`, `EnsureEtapaAtendimentosEncerrados` (cada funil
  semeado termina em "Fechamento" = ganho e "Atendimentos encerrados" = perdido).
- `RegistrarHistoricoNegociacao` (tabela `historicos_negociacao`: `tipo`, `titulo`,
  `descricao`, `autor`, `ocorrido_em`); `SyncProximaTarefaNegociacao`.
- `NegociacaoController::index` carrega todas as negociações para o kanban.
- `BuildDashboardMetrics::analiseAtendimentosEncerrados()` agrupa perdas por
  `motivo_desqualificacao`.
- `NegociacaoObserver`/`NegociacaoDisparaEventosMetaTest` — mudanças de etapa disparam eventos
  Meta CAPI. **Arquivar não deve disparar evento de conversão** (não muda etapa).
- Funil empresarial: slug `pj-meta-ads`.

## Decisão de desenho

Arquivar = **flag ortogonal à etapa**, não mover para "Atendimentos encerrados" e não deletar:
- preserva a etapa onde o lead parou (dado útil para relatório "onde o funil trava");
- não dispara eventos Meta por mudança de etapa;
- reversível.

## Passos

### 1. Dados
- Enum `App\Enums\MotivoArquivamento` (string): `Perdido = 'perdido'`,
  `Desqualificado = 'desqualificado'`, `Duplicado = 'duplicado'`,
  `SemRetorno = 'sem_retorno'`, `SemInteresse = 'sem_interesse'`, com `label()`.
- Migration em `negociacoes`: `arquivada_em` timestamp null indexado, `arquivada_por_user_id`
  foreignId null `->constrained('users')->nullOnDelete()`, `motivo_arquivamento` string(20)
  null, `observacao_arquivamento` text null, `duplicada_de_negociacao_id` foreignId null
  `->constrained('negociacoes')->nullOnDelete()` (só para motivo duplicado). Índice
  `(tenant_id, arquivada_em)`.
- Model: casts; scopes `ativas()` (`whereNull('arquivada_em')`) e `arquivadas()`;
  `isArquivada()`.
- **Não** usar global scope (esconderia arquivadas de relatórios e da busca). Em vez disso,
  aplicar `ativas()` explicitamente em: `NegociacaoController::index` (kanban), `crmCounts`
  (`HandleInertiaRequests`), `CalendarioController`, `AtividadeController`, `HojeController`,
  `BuildCenarioDoDia` (`negociacoesAbertas()`), lembretes, e os scopes de `TarefaNegociacao`
  do prompt 01 (`whereHas('negociacao', fn ($q) => $q->ativas())`). Mapear com
  `grep -rn "Negociacao::" app/` e decidir caso a caso — listar no README os pontos alterados.
- `BuildDashboardMetrics`: arquivadas contam como **saída do funil** (somar em perdas por
  motivo, usando `motivo_arquivamento` quando presente) e não contam como "em aberto".

### 2. Action `App\Actions\Crm\ArquivarNegociacoes`
`__invoke(Collection|array $negociacaoIds, MotivoArquivamento $motivo, ?string $observacao,
User $por, ?int $duplicadaDe = null): int` — em transação:
- ignora já arquivadas e as de outro tenant (escopo do tenant atual);
- grava os campos de arquivamento;
- **cancela** as tarefas abertas (`status = cancelada`) para que não poluam Hoje/Atividades/
  lembretes, e chama `SyncProximaTarefaNegociacao`;
- registra no histórico: "Arquivada — Sem retorno" com observação e autor;
- se `motivo = duplicado`, exige `duplicadaDe` (negociação ativa do mesmo tenant) e registra
  no histórico de ambas;
- retorna a quantidade arquivada.
Action inversa `DesarquivarNegociacao` (limpa campos, histórico "Reaberta", **não** reabre
tarefas canceladas — o usuário cria um novo follow-up; a tela deve sugerir isso).

### 3. Tela Saneamento
- Rota `GET negociacoes/saneamento` → `SaneamentoFunilController@index`, `POST
  negociacoes/saneamento/arquivar`, `PATCH negociacoes/{negociacao}/desarquivar`. Acesso:
  owner/admin/gerente (checar slugs de `App\Models\Role`; vendedor recebe 403). Item no menu
  no grupo Comercial (ou atalho no topo do kanban "Sanear funil (N)").
- **Sugestões** (critérios combináveis, com contagem por critério, default funil
  `pj-meta-ads` se existir, senão o primeiro):
  - parado na etapa há ≥ N dias (`etapa_desde`, N default 30, editável);
  - SLA da etapa estourado há ≥ X dias;
  - follow-up/tarefa vencida há ≥ X dias sem conclusão;
  - sem nenhuma tarefa aberta;
  - possível duplicado: mesmo `contato_id` com mais de uma negociação ativa no mesmo funil
    (sugere motivo "duplicado" e pré-seleciona a mais recente como principal).
- Tabela paginada: contato, empresa, etapa, dias parado, última atividade
  (`historicos_negociacao.ocorrido_em` mais recente), responsável, valor, link para o drawer.
- Seleção em massa (checkbox + "selecionar todos desta página") → modal com **motivo
  obrigatório** (radio dos 5), observação opcional; botão confirma "Arquivar N negociações".
  `FormRequest` valida motivo, ids (`array`, max 500) e `duplicada_de` quando motivo =
  duplicado.
- Aba "Arquivadas": lista com filtros motivo/período/responsável e botão Desarquivar.
- No drawer/edição da negociação: ação individual "Arquivar" (mesmo modal) e, se arquivada,
  faixa "Arquivada em dd/mm por X — motivo" + Desarquivar. Tela de edição continua acessível
  para arquivadas (dados preservados).

## Fora de escopo
Arquivamento automático agendado. Exclusão definitiva. Alterar o fluxo de etapa
"Atendimentos encerrados".

## Testes (`tests/Feature/SaneamentoFunilTest.php`)
- sugestão "parado há ≥ 30 dias" traz a negociação certa e não traz arquivada;
- arquivar em massa grava motivo/autor/data, cancela tarefas abertas, atualiza
  `proxima_tarefa`, registra histórico;
- motivo ausente/ inválido → 422; duplicado sem `duplicada_de` → 422;
- arquivada some do kanban (`negociacoes.index`), de Atividades, Hoje, lembretes e
  `crmCounts`, mas abre em `negociacoes.edit`;
- desarquivar volta ao kanban na mesma etapa;
- arquivar **não** dispara job de evento Meta (`Queue::fake()` + assert not pushed);
- vendedor recebe 403; outro tenant não arquiva ids alheios;
- dashboard conta arquivada como saída por motivo.

## Pronto quando
Suíte verde, phpstan/vue-tsc ok, build ok; README desta pasta lista todos os pontos onde
`ativas()` foi aplicado; manualmente: no funil `pj-meta-ads`, selecionar leads parados,
arquivar como "sem retorno" → somem do kanban e aparecem na aba Arquivadas com histórico.

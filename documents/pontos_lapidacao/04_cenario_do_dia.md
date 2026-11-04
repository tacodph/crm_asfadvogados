# Prompt 04 — Cenário do dia (visão macro) no topo da página Hoje

> Pré-requisitos: prompts 01–03 (página `crm/Hoje` com `HojeController`, scopes de tarefa).

## Objetivo (item 5 do cliente)

Uma visão inicial e objetiva do cenário comercial do dia, para o responsável saber, logo no
início do dia, **onde concentrar esforços**. Mostrar rapidamente:
- quantidade de leads recebidos;
- follow-ups previstos;
- follow-ups pendentes ou atrasados;
- reuniões agendadas;
- oportunidades em cada etapa do funil;
- atividades que exigem atenção.

## Contexto do repo (verificado)

- Lead recebido = `Negociacao` criada (entradas: `CapturarLeadTrafegoController`
  `POST /api/trafego/leads`, Meta Ads sync, e-mail IMAP `RegistrarLeadEmail`, cadastro manual).
  Origem/canal: `negociacoes.canal_contato_id`, `origem_utm` (json), colunas `meta_*`.
- `negociacoes`: `funil_id`, `etapa_funil_id`, `responsavel_user_id`, `etapa_desde`,
  `proxima_tarefa_em`, `concluida_em`, `created_at`.
- `etapas_funil`: `sla` (dias), `resultado` (`EtapaFunilResultado`: aberta/ganho/perdido),
  `ordem`, `cor_fundo`/`cor_texto`/`cor_suave`.
- `App\Actions\Crm\BuildDashboardMetrics` — **não** reaproveitar inteiro (é analítico por
  período e pesado); reaproveitar apenas helpers pontuais se existirem (ex.: classificação de
  etapa encerrada).
- Funis: `b2b`, `pf`, `pj-meta-ads` (empresarial). Vários lugares tratam `slug === 'b2b'`
  como "B2B consultivo".

## Passos

### 1. Action `App\Actions\Crm\BuildCenarioDoDia`
Assinatura: `__invoke(?User $responsavel, ?Funil $funil, CarbonInterface $dia): array`
(`$responsavel = null` = time inteiro). Retorna:
- `leadsRecebidos`: `{ hoje, ontem, porCanal: [{canal, total}] }` — negociações com
  `created_at` no dia (comparação de dia no fuso da app).
- `followUps`: `{ previstosHoje, concluidosHoje, atrasados }`.
- `reunioesHoje`: total + próxima (hora, contato).
- `atividadesAtrasadas`: total (todos os tipos).
- `funil`: para o funil selecionado (default: o funil com mais negociações abertas do
  responsável), lista de etapas **abertas** em ordem com `{ etapaId, nome, cores, total,
  valorTotal, foraDoSla }`, onde `foraDoSla` = negociações com `etapa_desde` + `sla` dias < hoje.
- `exigemAtencao` (lista curta, máx. 5 por grupo, com link `?negociacao=ID`):
  - negociações abertas **sem nenhuma tarefa aberta** (ninguém vai retomar);
  - negociações com SLA da etapa estourado;
  - leads de hoje ainda **sem responsável** ou sem primeira tarefa.
- Só negociações em etapa `resultado = aberta` (e, após o prompt 05, `ativas()` — deixar o
  ponto de extensão óbvio: uma única base query `negociacoesAbertas()` no Action).
- Queries agregadas (`count`, `sum`, `groupBy`), sem carregar coleções inteiras; compatível
  com sqlite (testes) e MySQL.

### 2. Controller e página
- `HojeController@index` aceita `funil` e `escopo` (já existente do 03) e passa `cenario`
  como prop **deferida** (`Inertia::defer(fn () => ...)`) para a lista do dia aparecer primeiro.
- `crm/Hoje.vue`: bloco "Cenário do dia" no topo:
  - linha de KPIs (cards no estilo dos KPIs de `Atividades.vue`): Leads hoje (com Δ vs ontem),
    Follow-ups previstos, **Follow-ups atrasados** (vermelho se > 0), Reuniões hoje,
    Atividades atrasadas;
  - "Funil agora": barras horizontais simples (CSS, sem lib de gráfico — o repo não tem uma)
    por etapa com total e marcador "N fora do SLA"; clique na etapa leva ao kanban filtrado
    por etapa;
  - "Exigem atenção": lista agrupada com links diretos;
  - seletor de funil + escopo minhas/time (reutiliza o do prompt 03);
  - skeleton enquanto a prop deferida carrega.
- Cada KPI é clicável e rola até a seção correspondente da página (ou abre Atividades com o
  filtro equivalente).

## Fora de escopo
Atualização automática (o dashboard ao vivo é o prompt 07; aqui basta o poll do sino já
existente). Arquivamento (05).

## Testes
- `tests/Unit` ou `tests/Feature/BuildCenarioDoDiaTest.php` com `Carbon::setTestNow()`:
  - leads de hoje × ontem por canal;
  - follow-ups previstos/atrasados/concluídos hoje;
  - etapas abertas com totais e `foraDoSla` (etapa com `sla = 2`, `etapa_desde` há 5 dias);
  - etapas ganho/perdido não entram;
  - `exigemAtencao` inclui negociação sem tarefa aberta e exclui a que tem;
  - filtro por responsável e por funil;
  - isolamento de tenant.
- `HojePageTest`: prop deferida `cenario` resolve via partial reload.

## Pronto quando
Suíte verde, phpstan/vue-tsc ok, build ok; abrir Hoje mostra o cenário com números que
batem com o kanban e com Atividades para o mesmo escopo.

# Prompt 06 — Pesquisa dentro do funil

> Pré-requisitos: prompts 01–05 (em especial o arquivamento do 05, para o filtro de status
> "arquivada").

## Objetivo (item 3 do cliente)

Uma ferramenta de pesquisa dentro do funil para localizar rapidamente um lead/oportunidade sem
percorrer as etapas manualmente. Localizar, no mínimo, por:
- nome;
- telefone;
- responsável;
- etapa do funil;
- status.

## Contexto do repo (verificado)

- `NegociacaoController::index` → `crm/Negociacoes.vue` (≈1160 linhas): carrega **todas** as
  negociações do tenant como `NegociacaoResource::collection`, alterna funil (`funilId`) e
  visão kanban/lista (`visao`); filtros client-side em dropdown (etapas, empresas, contatos,
  canais, status de atendimento, qualificação, responsáveis — refs ~linhas 35-41,
  `passaFiltro`/`negociacoesFiltradas` ~133-150). **Não há campo de texto de busca.**
- `?negociacao=ID` abre o `CrmNegociacaoDrawer`.
- Telefone: `contatos.telefone` só dígitos (DDD + número), normalizado por
  `App\Actions\Crm\NormalizeContatoIdentifiers::telefone()`. Contatos têm também `email`,
  `cpf`; empresas têm nome/CNPJ.
- Status possíveis de uma negociação: etapa `resultado` (aberta/ganho/perdido), arquivada
  (prompt 05), além de `status_atendimento_id` / `status_qualificacao_id` (tabelas
  `StatusAtendimento`, `StatusQualificacao`).

## Decisão de desenho

Busca **no servidor** (endpoint JSON), não só filtro client-side: precisa achar arquivadas
(fora do kanban), funcionar em qualquer funil e escalar quando o kanban deixar de carregar
tudo. O kanban continua com seus filtros atuais.

## Passos

### 1. Endpoint
- `GET negociacoes/busca` → `NegociacaoBuscaController` (invokable), nome
  `negociacoes.busca`, retorna JSON (`JsonResource`), tenant-scoped.
- Parâmetros (FormRequest): `q` (string, min 2), `responsavel` (user id), `funil`, `etapa`,
  `status` ∈ `aberta|ganho|perdido|arquivada|todas` (default `aberta` = etapa aberta e
  ativa), `status_atendimento`, `limit` (default 20, max 50).
- Interpretação de `q`:
  - se, após `NormalizeContatoIdentifiers::telefone()`, sobrarem ≥ 4 dígitos → busca por
    telefone com `LIKE %digitos%` em `contatos.telefone` (aceita "(11) 98765-4321", "98765",
    "+55 11 ..." — remover prefixo `55` quando o número tiver 12-13 dígitos);
  - senão → `LIKE %q%` (case-insensitive, sem acento se viável no sqlite/MySQL — usar
    collation do MySQL em produção; em sqlite aceitar sensível a acento e documentar) em
    `contatos.nome`, `empresas.nome`, `negociacoes.assunto`, `contatos.email`.
  - Também aceitar `#123` → id da negociação.
- Ordenação: match exato de telefone/nome primeiro, depois ativas antes de arquivadas, depois
  `updated_at` desc.
- Resposta por item: `id`, `assunto`, `contato {nome, telefone formatado}`, `empresa`,
  `funil {id, nome}`, `etapa {id, nome, cores}`, `status` (`aberta|ganho|perdido|arquivada`
  + `motivoArquivamento`), `responsavel`, `valor`, `proximaTarefa` (com `prazoRotulo`),
  `url` (`negociacoes.index?negociacao=ID&funil=...`).
- Índices: conferir se `contatos.nome` e `negociacoes.assunto` têm índice; criar se a busca
  ficar lenta (documentar decisão; `LIKE '%x%'` não usa índice B-tree — aceitável no volume
  atual, registrar como ponto de atenção).

### 2. UI no funil
- `Negociacoes.vue`: barra de pesquisa fixa no topo do kanban (ícone lupa, placeholder
  "Buscar por nome, telefone, empresa ou #id"), atalho **`/`** para focar e `Esc` para
  limpar. Ao lado, filtros: Responsável, Etapa (do funil atual ou todos), Status.
- Resultado em painel suspenso (combobox reka-ui) com debounce 300 ms (`useDebounceFn` do
  `@vueuse/core`, já instalado), chamando o endpoint com `fetch`/`axios` existente ou
  `useHttp` do Inertia v3 — escolher o padrão já usado no repo (verificar
  `ContatoController@duplicatas`, que já é um endpoint JSON consumido pelo front).
- Selecionar um resultado: troca `funilId` se necessário, abre o drawer (`?negociacao=ID`)
  e destaca o card no kanban (scroll + pulso). Para arquivada, abre o drawer com a faixa
  "Arquivada" do prompt 05.
- Estado vazio: "Nenhuma negociação encontrada" + atalho para buscar em "todas" se o status
  filtrado era "aberta".
- Opcional (se couber sem retrabalho): mesma busca acessível pelo header (`CrmHeader.vue`)
  com `Ctrl+K`, reaproveitando o componente.

## Fora de escopo
Busca full-text (Scout/Meilisearch). Paginar o kanban.

## Testes (`tests/Feature/NegociacaoBuscaTest.php`)
- nome parcial do contato; nome da empresa; assunto;
- telefone com máscara, com +55 e só parte dos dígitos;
- `#id`;
- filtro responsável, etapa, status ganho/perdido/arquivada/todas;
- default não traz arquivadas nem ganhas/perdidas;
- isolamento de tenant (mesmo nome em outro tenant não aparece);
- `q` com 1 caractere → 422; `limit` respeitado;
- usuário não autenticado → redirect/401.

## Pronto quando
Suíte verde, phpstan/vue-tsc ok, build ok; manualmente: no kanban, `/` + digitar parte do
telefone de um lead em outra etapa → resultado aparece e abre o drawer da negociação.

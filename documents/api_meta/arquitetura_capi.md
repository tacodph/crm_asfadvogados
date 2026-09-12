# Arquitetura — API de Conversões da Meta (CAPI)

> Documento de referência canônico da integração. Nomes de tabelas, colunas, enums, jobs,
> actions e rotas aqui definidos são a fonte da verdade para os prompts de implementação
> (`documents/api_meta/prompts/api_conversoes_meta/` — cópia em
> `D:\ProjetosSandro\APIS\api_meta\docs\prompts\api_conversoes_meta\`).
>
> Base: `documents/api_meta/descricao_api_conversoes_meta.md`.
> Cliente/escopo: ASF Advogados (tenant único hoje), Instagram
> `https://www.instagram.com/asfadvogados_/`, duas campanhas com Pixels distintos —
> **Bancário** (`2050053805929053`) e **Concurso** (`1536850061554800`).

---

## 1. Visão de engenharia de dados

A CAPI é um **pipeline de saída de eventos** (server-side): fatos de negócio do CRM
(lead criado, etapa avançou, negócio ganho) são transformados em eventos padronizados da
Meta, enfileirados, enviados à Graph API e registrados com o resultado.

Princípios:

| Princípio | Como é garantido |
|---|---|
| **Idempotência** | Todo evento tem `event_id` estável e único por campanha. Reenvio nunca duplica na Meta (ela deduplica por `event_id` + `event_name`). |
| **Fila primeiro** | Nenhuma chamada HTTP à Meta no ciclo de request do usuário. Tudo via job `EnviarEventoConversaoMeta` na fila `meta-capi`. |
| **Fonte de verdade local** | `meta_conversao_eventos` registra 100% dos disparos (request hasheado + resposta: `fbtrace_id`, `events_received`, erro). A API da Meta é fraca para leitura — o CRM não depende dela para relatórios. |
| **PII sob LGPD** | E-mail/telefone/nome só saem em SHA-256 (Advanced Matching) e **somente com consentimento** da finalidade de marketing concedido. Sem consentimento → evento gravado como `descartado`, sem PII no payload. |
| **Segredo cifrado, fora do `.env`** | `pixel_id` + `access_token` de cada campanha vivem em `meta_conversao_configs`. O token é cifrado em repouso por chave dedicada (`META_CAPI_ENCRYPTION_KEY`, independente do `APP_KEY`) via cast `App\Casts\SegredoMeta`. |
| **Retenção** | `request_payload` / `response_body` expurgados após `config('meta.capi.retencao_dias')` (default 180). Metadados (`fbtrace_id`, `status`, contadores) permanecem. |
| **Isolamento multi-tenant** | Todas as tabelas usam `BelongsToTenant` + `TenantScope` (fail-closed). |

---

## 2. Pipeline

```mermaid
flowchart TD
    A[Fato no CRM<br/>Negociacao created / updated] --> B{NegociacaoObserver}
    B -->|mapeia fato -> MetaEventName| C[Action RegistrarEventoConversaoMeta]

    C --> D[ResolverCampanhaConversao<br/>funil/canal -> MetaConversaoConfig]
    D -->|campanha inativa / indefinida| X1[(meta_conversao_eventos<br/>status = descartado)]
    D -->|ok| E{Gate de consentimento<br/>ConsentimentoContato = concedido?}
    E -->|não| X2[(meta_conversao_eventos<br/>status = descartado<br/>motivo = sem_consentimento<br/>sem PII)]
    E -->|sim| F[AdvancedMatching + CapiPayloadBuilder<br/>PII -> SHA-256]

    F --> G[(meta_conversao_eventos<br/>status = pendente<br/>event_id determinístico<br/>request_payload hasheado)]
    G --> H[dispatch EnviarEventoConversaoMeta<br/>queue: meta-capi]

    H --> I[ConversionsApiClient::enviar]
    I -->|POST graph.facebook.com/{v}/{pixel_id}/events| J[(Meta Graph API)]
    J --> K{resposta}
    K -->|events_received >= 1| L[(evento: status = enviado<br/>fbtrace_id, events_received, enviado_em)]
    K -->|erro permanente 190/100| M[(evento: status = erro<br/>não relança)]
    K -->|erro transitório 429/5xx| N[fila re-tenta<br/>backoff 1m..3h]

    L --> O[atualiza MetaConversaoConfig<br/>ultimo_evento_em, ultimo_status = ok]

    subgraph Observabilidade
        P[Scheduler diário 05:30<br/>meta:sincronizar-estatisticas] --> Q[SincronizarEstatisticasPixelMeta]
        Q -->|GET /{pixel_id}?fields=last_fired_time + /stats| J
        Q --> R[(meta_conversao_estatisticas<br/>snapshot por dia)]
    end

    subgraph UI /trafego
        S[/trafego — CRUD de campanhas<br/>+ testar conexão + evento de teste/]
        T[/trafego/eventos — log filtrável/]
        U[/trafego/diagnostico — stats + EMQ proxy/]
    end
    G -.-> T
    L -.-> T
    R -.-> U
```

---

## 3. Modelo de dados

Três tabelas, todas com `id`, `tenant_id` (FK `tenants`, `nullable()->index()`, **fora de
`#[Fillable]`**, preenchida por `BelongsToTenant`) e `timestamps`.

### 3.1 `meta_conversao_configs` — 1 linha por campanha, por tenant

| coluna | tipo | notas |
|---|---|---|
| `nome_campanha` | string(120) | ex.: `Bancário` |
| `slug` | string(120) | ex.: `bancario` — `unique(tenant_id, slug)` |
| `pixel_id` | string | dataset/pixel ID (não é segredo) |
| `access_token` | text | **cifrado** por `App\Casts\SegredoMeta`; `$hidden` |
| `test_event_code` | text nullable | **cifrado** por `SegredoMeta`; `$hidden` |
| `token_ultimos4` | string(8) nullable | em claro, só para exibir (`••••ZDZD`); mantido pelo hook `saving` |
| `token_verificado_em` | timestamp nullable | último "testar conexão" |
| `token_valido` | boolean nullable | resultado do último teste (`null` = nunca testado) |
| `api_version` | string | default `v21.0` |
| `action_source` | string | default `system_generated`; `website` quando há navegador na origem |
| `origem_url` | string nullable | ex.: URL da landing / link-tree do Instagram |
| `finalidade_consentimento_slug` | string nullable | slug de `FinalidadeConsentimento` exigido para disparar |
| `ativo` | boolean | default `true` |
| `ultimo_evento_em` | timestamp nullable | telemetria |
| `ultimo_status` | string nullable | `ok` / `erro` |
| `atualizado_por_user_id` | FK `users` nullable | `nullOnDelete()` — auditoria de quem trocou o token |

Índices: `unique(tenant_id, slug)`, `index(tenant_id, pixel_id)`, `index(tenant_id, ativo)`.

Model `App\Models\MetaConversaoConfig`:
- `casts()`: `access_token` / `test_event_code` → `SegredoMeta::class`; `ativo`,
  `token_valido` → `boolean`; `token_verificado_em`, `ultimo_evento_em` → `datetime`.
- `$hidden = ['access_token', 'test_event_code']`.
- hook `saving`: se `access_token` dirty e não-nulo → `token_ultimos4 = substr(token, -4)` e
  zera `token_verificado_em` / `token_valido`.
- `mascararToken(): string` usa `token_ultimos4` (**nunca** decifra na UI).
- `scopeAtivas()`, relações `eventos()` / `estatisticas()` (`HasMany`).

### 3.2 `meta_conversao_eventos` — log append-only de cada disparo

| coluna | tipo | notas |
|---|---|---|
| `meta_conversao_config_id` | FK nullable | `nullOnDelete()` — evento `descartado` por campanha indefinida ainda é auditável |
| `event_name` | string | cast `MetaEventName` |
| `event_id` | string | **determinístico** — `unique(meta_conversao_config_id, event_id)` |
| `event_time` | timestamp | hora do fato (unix no envio) |
| `action_source` | string | |
| `negociacao_id` / `contato_id` | FK nullable | `nullOnDelete()` |
| `status` | string | cast `MetaConversaoEventoStatus` — `pendente` / `enviado` / `erro` / `descartado` |
| `motivo_descarte` | string nullable | `sem_consentimento`, `campanha_inativa`, `campanha_indefinida` |
| `tentativas` | unsignedTinyInteger | default 0 |
| `http_status` | unsignedSmallInteger nullable | |
| `events_received` | unsignedInteger nullable | do corpo da resposta |
| `fbtrace_id` | string nullable | |
| `error_code` / `error_message` | string / text nullable | `error_message` inclui `error_user_msg` quando houver |
| `request_payload` | json | **PII já em SHA-256** — nunca dado cru |
| `response_body` | json nullable | resposta crua (sem token) |
| `enviado_em` | timestamp nullable | |
| `is_teste` | boolean | default `false` (eventos disparados pela tela) |

Índices: `index(tenant_id, event_name, created_at)`, `index(tenant_id, status)`.

### 3.3 `meta_conversao_estatisticas` — snapshot diário por campanha (dados lidos da Meta)

| coluna | tipo | notas |
|---|---|---|
| `meta_conversao_config_id` | FK | |
| `referencia` | date | `unique(meta_conversao_config_id, referencia)` |
| `pixel_last_fired_at` | timestamp nullable | de `GET /{pixel_id}?fields=last_fired_time` |
| `eventos_servidor` / `eventos_navegador` / `eventos_deduplicados` | unsignedInteger nullable | agregações (best-effort) |
| `match_rate` | decimal(5,2) nullable | quando disponível |
| `payload_bruto` | json | resposta crua da Graph API para auditoria |

> **Limitação real da API da Meta para leitura.** A CAPI **não** tem endpoint para reler os
> eventos enviados. O que existe:
> - `GET /{pixel_id}?fields=last_fired_time,name,is_created_by_business,data_use_setting`
> - `GET /{pixel_id}/stats?aggregation=<...>&start=<unix>&end=<unix>` (campos variáveis,
>   tratar como best-effort e guardar `payload_bruto`)
> - Event Match Quality (EMQ) **não** tem endpoint estável → a UI mostra link para o
>   Events Manager e calcula um **proxy local**: % de eventos enviados com `em` + `ph`
>   preenchidos.
> Por isso `meta_conversao_eventos` é a fonte de verdade dos relatórios.

---

## 4. Enums (`app/Enums/`)

- **`MetaConversaoEventoStatus`** (`string`): `Pendente='pendente'`, `Enviado='enviado'`,
  `Erro='erro'`, `Descartado='descartado'`; `label(): string` em PT-BR.
- **`MetaEventName`** (`string`): `Lead`, `Contact`, `Schedule`, `SubmitApplication`,
  `CompleteRegistration`, `Purchase` (value == nome exato da Meta).

Mapa fato-de-negócio → evento Meta:

| Fato na `Negociacao` | Evento Meta | Gatilho |
|---|---|---|
| criada | `Lead` | `created` |
| contato (telefone/e-mail) preenchido pela 1ª vez | `Contact` | `updated` (dirty em campos de contato) |
| avançou p/ etapa de reunião/agendamento | `Schedule` | `updated` `etapa_funil_id` |
| avançou p/ etapa "proposta enviada" | `SubmitApplication` | `updated` `etapa_funil_id` |
| marcada como ganha (etapa final de sucesso) | `Purchase` (`value` + `currency=BRL`) | `updated` `etapa_funil_id` |

O disparo é embrulhado em `try/catch` com `report($e)` — **nunca** pode quebrar o `save()`
da negociação.

---

## 5. Contrato de deduplicação (`event_id`)

1. **Origem com navegador** (formulário do site / link-tree do Instagram): o front-end
   envia, junto do lead, os campos capturados pelo Pixel do navegador —
   `event_id`, `fbp`, `fbc`, `event_source_url`, `client_ip_address`, `client_user_agent`.
   Esses valores são **persistidos no lead** (colunas `meta_*` em `negociacoes`) e
   **reenviados** na CAPI com o **mesmo `event_id`** → a Meta descarta o duplicado.
2. **Origem sem `event_id`**: gerar determinístico
   `sprintf('%s_%d', mb_strtolower($event_name), $negociacao->id)` — ex.: `lead_1042`.
3. Reprocessar um evento **nunca** muda o `event_id` → reenvio é idempotente
   (`firstOrCreate` por `meta_conversao_config_id` + `event_id`).

### Integração com o Pixel do site (front-end externo)

O snippet do Pixel na landing/site da ASF deve emitir o mesmo `event_id`:

```js
// gera/recupera um id por sessão de lead
const eventId = crypto.randomUUID();

fbq('track', 'Lead', { /* custom_data */ }, { eventID: eventId });

// e enviar ao CRM junto do lead:
POST https://api.asfadvogados.adv.br/v1/trafego/leads
{
  ...dadosDoLead,
  meta_event_id: eventId,
  meta_fbp: getCookie('_fbp'),
  meta_fbc: getCookie('_fbc'),
  meta_event_source_url: location.href
}
```

`client_ip_address` e `client_user_agent` são capturados **no servidor** que recebe o POST
(request do navegador), não pelo JS. Com `event_name` idêntico + `eventID` == `meta_event_id`,
navegador e CAPI deduplicam perfeitamente.

---

## 6. LGPD / PII

- **Advanced Matching** (`App\Support\Meta\AdvancedMatching`): `em`, `ph`, `fn`, `ln`,
  `ct`, `st`, `zp`, `country`, `external_id` normalizados (trim + lowercase + remoção de
  máscara; telefone recebe DDI `55` quando ausente) e **SHA-256** antes de sair. Cada campo
  vai como array de 1 hash (`"em": ["<hash>"]`). Campos nulos são omitidos.
- `client_ip_address` / `client_user_agent` **não** são hasheados (exigência da Meta) — só
  são enviados quando `action_source = website` **e** há consentimento.
- **Gate de consentimento**: disparo só ocorre se o `Contato` tiver `ConsentimentoContato`
  com `StatusConsentimento.slug = concedido` para a finalidade
  `config.finalidade_consentimento_slug`. Regras fail-closed:
  - finalidade não configurada **ou** não encontrada → tratado como **sem consentimento**;
  - sem consentimento → linha gravada com `status = descartado`,
    `motivo_descarte = sem_consentimento`, `request_payload` **sem** `user_data`
    (só `event_name`, `event_id`, `event_time`, `action_source`), **sem** dispatch.
- **Retenção**: `meta:capi-expurgar-payloads` (ou `MassPrunable`) zera
  `request_payload` / `response_body` após `config('meta.capi.retencao_dias')`.

---

## 7. Configuração (DB-first — sem credenciais no `.env`)

| Camada | Onde | Conteúdo |
|---|---|---|
| Aplicação | `.env` → `config/meta.php` | `META_CAPI_API_VERSION`, `META_CAPI_QUEUE`, `META_CAPI_RETENCAO_DIAS`, `META_CAPI_DEFAULT_ACTION_SOURCE`, `META_CAPI_INSTAGRAM_URL`, `META_CAPI_TEST_EVENT_CODE` |
| Chave de cifra | `.env` | `META_CAPI_ENCRYPTION_KEY` (`base64:<32 bytes>`, gerada por `php artisan meta:gerar-chave`, **independente** do `APP_KEY`) |
| Credenciais de campanha | **banco** (`meta_conversao_configs`) | `pixel_id`, `access_token` (cifrado), `test_event_code` (cifrado), políticas por campanha |

- Cadastro das campanhas: tela **`/trafego`** (CRUD) ou comando **`php artisan meta:campanha`**
  (headless — token via `password()`).
- Cada ambiente cadastra suas próprias credenciais; nada de segredo em arquivo versionado.
- **Rotação da chave**: `php artisan meta:recriptografar-tokens --chave-antiga=<...>`
  decifra com a antiga e recifra com a atual, por tenant, em transação. Perder a
  `META_CAPI_ENCRYPTION_KEY` sem rotacionar = **perder todos os tokens** (ver runbook).

---

## 8. Componentes de código (nomes canônicos)

| Camada | Classe | Papel |
|---|---|---|
| Cast | `App\Casts\SegredoMeta` | cifra/decifra token com chave dedicada |
| Support | `App\Support\Meta\AdvancedMatching` | normalização + SHA-256 de PII |
| Support | `App\Support\Meta\CapiPayloadBuilder` | monta o `data[]` do evento |
| Support | `App\Support\Meta\ConversionsApiClient` | `enviar()` (POST /events) e `verificarPixel()` (GET /{pixel_id}) |
| Support | `App\Support\Meta\ResolverCampanhaConversao` | funil/canal → `MetaConversaoConfig` |
| Action | `App\Actions\Meta\RegistrarEventoConversaoMeta` | cria a linha + gate + dispatch |
| Action | `App\Actions\Meta\SincronizarEstatisticasPixelMeta` | snapshot diário |
| Job | `App\Jobs\EnviarEventoConversaoMeta` | envia e atualiza a linha (retry/backoff, `WithoutOverlapping`) |
| Observer | `App\Observers\NegociacaoObserver` (ou listeners) | mapeia fatos → eventos |
| Controllers | `TrafegoController`, `TrafegoEventoController`, `TrafegoDiagnosticoController` | UI |
| Commands | `meta:gerar-chave`, `meta:campanha`, `meta:sincronizar-estatisticas`, `meta:capi-reprocessar`, `meta:capi-expurgar-payloads`, `meta:recriptografar-tokens` | operação |

## 9. Rotas (grupo `auth` + `verified`, `routes/web.php`)

```
GET    trafego                              trafego.index
POST   trafego                              trafego.store
PATCH  trafego/{config}                     trafego.update
DELETE trafego/{config}                     trafego.destroy
POST   trafego/{config}/testar-conexao      trafego.testar-conexao
POST   trafego/{config}/evento-teste        trafego.evento-teste
GET    trafego/eventos                      trafego.eventos.index
GET    trafego/eventos/{evento}             trafego.eventos.show
POST   trafego/eventos/{evento}/reenviar    trafego.eventos.reenviar
GET    trafego/diagnostico                  trafego.diagnostico.index
POST   trafego/diagnostico/sincronizar      trafego.diagnostico.sincronizar
```

Páginas Inertia: `crm/Trafego.vue`, `crm/TrafegoEventos.vue`, `crm/TrafegoDiagnostico.vue`
(layout `CrmLayout` automático; `PAGE_SCREEN` mapeia as três para o screen `trafego`).

---

## 10. Decisões em aberto / a confirmar na implementação

- Slug real da **finalidade de consentimento** de marketing do tenant ASF
  (`FinalidadeConsentimento`) — o `.env`/UI usa `marketing` como placeholder.
- Slug do **tenant** ASF: `asfadvogados` (confirmado — `BackfillLegacyTenant`).
- Versão da Graph API: iniciar em `v21.0`; revisar contra a versão estável vigente.
- Colunas `meta_*` de rastreio: ficam em **`negociacoes`** (migration `2026_09_01_000004`) —
  `meta_event_id`, `meta_fbp`, `meta_fbc`, `meta_event_source_url` (input do front, no Fillable),
  `meta_client_ip`, `meta_client_user_agent`, `meta_captado_em` (preenchidos pelo controller).
- Existência de papel/policy para "gerir tráfego" (`app/Models/Role.php`) → autorização
  das rotas `/trafego*`. **Decidido (prompt 05):** sem policy — `authorize()` dos
  FormRequests devolve `$this->user() !== null`, alinhado a `SetorController` e demais
  controllers de catálogo do CRM. Se surgir a necessidade de restringir a gestores,
  criar `MetaConversaoConfigPolicy` e trocar os `authorize()`.

## 11. Estado da implementação (prompts 01–07)

- **Prompt 07 ✅** — fechamento: testes, operação, observabilidade, runbook.
  - **Canal de log `meta-capi`** (`config/logging.php`, daily 14d,
    `storage/logs/meta-capi-*.log`). Job + client + Action passaram a logar nele
    (`Log::channel('meta-capi')`). Só ids/status — **sem token, IP ou UA**.
  - **Comandos operacionais** (todos iteram os tenants em `CurrentTenant::runAs`):
    - `meta:capi-reprocessar {--status=erro} {--campanha=} {--desde=} {--limite=500} {--force}`
      — recoloca eventos na fila mantendo `event_id`; confirma a menos de `--force`.
    - `meta:capi-expurgar-payloads` — LGPD: zera `request_payload`/`response_body` de
      eventos `enviado` mais velhos que `retencao_dias` (preserva `fbtrace_id`, `status`,
      contadores). Agendado semanal (seg 04:00).
    - `meta:recriptografar-tokens {--chave-antiga=} {--force}` — rotação da
      `META_CAPI_ENCRYPTION_KEY`; dry-run por padrão, transação por tenant, `saveQuietly`
      (não zera `token_verificado_em`), nunca imprime tokens. `SegredoMeta::encrypterParaChave()`
      exposto p/ decifrar com a chave antiga.
  - **Health**: card **Saúde** em `/trafego` (prop `saude` no `TrafegoController@index`) —
    erros nas últimas 24h, `Queue::size('meta-capi')`, idade do último envio por campanha.
    Optou-se por card em vez de rota `GET /trafego/health` (menos superfície).
  - **Schedule**: `->withoutOverlapping()->onOneServer()` nos 2 jobs agendados.
  - **Testes** (+14, total 271 verdes / 2 skip): `CapiResultadoTest` (unit, `retentavel()`),
    `MetaCapiReprocessarTest`, `MetaCapiExpurgarPayloadsTest`, `MetaCapiRecriptografarTokensTest`
    (2 chaves `base64:` fixas), `MetaCapiIsolamentoTenantTest` (tenant B não vê nada em
    `/trafego*`), `SincronizarEstatisticasPixelMetaTest` (upsert/dia + erro de API não lança).
    `Http::preventStrayRequests()` em todos os testes que tocam a Meta. Matriz do prompt já
    coberta pelos prompts 01–06: `AdvancedMatchingTest`, `CapiPayloadBuilderTest`,
    `SegredoMetaTest`, `ConversionsApiClientTest`, `EnviarEventoConversaoMetaTest`,
    `RegistrarEventoConversaoMetaTest`, `NegociacaoDisparaEventosMetaTest`,
    `ResolverCampanhaConversaoTest`, `MetaConversaoConfigTest`, `TrafegoTest`, `TrafegoEventoTest`.
  - **Runbook**: [`runbook_capi.md`](runbook_capi.md).
  - `grep -r EAA storage/logs` → vazio depois da suíte.

- **Prompt 06 ✅** — telas de observabilidade.
  - `/trafego/eventos` (`TrafegoEventoController` + `crm/TrafegoEventos.vue`): log
    `meta_conversao_eventos` paginado (`->paginate(25)->withQueryString()`, primeira
    tela paginada do CRM), filtros por campanha/status/event_name/período/busca via
    `router.get` parcial, drawer de detalhe (`CrmDrawer`) com `request_payload`
    (hashes, sem PII crua) e `response_body`. `reenviar` só aceita status `erro`
    (o modelo não gera "descartado por erro transitório" — os descartes são
    consentimento/campanha; reavaliar esses é outro fluxo).
  - `/trafego/diagnostico` (`TrafegoDiagnosticoController` + `crm/TrafegoDiagnostico.vue`):
    totais locais + proxy EMQ local (% de enviados com `em`/`ph`/`fbp|fbc`/`ip+ua`,
    lido dos `request_payload`), erros por `error_code`, mini-série 30d dos snapshots,
    link "ver no Events Manager". Prop `meta` é `Inertia::defer` (leitura ao vivo da
    Graph API por campanha, skeleton pulsante).
  - `SincronizarEstatisticasPixelMeta` (Action + Command `meta:sincronizar-estatisticas`,
    itera todos os tenants em `CurrentTenant::runAs`): `updateOrCreate` por
    `[config, referencia=startOfDay]` — `pixel_last_fired_at` é o campo confiável; o
    resto de `/stats` vai cru em `payload_bruto` (chaves não documentadas de forma
    estável — parsing best-effort marcado com `ponytail:`). Agendado
    `dailyAt('05:30')->withoutOverlapping()` em `routes/console.php`.
  - `ConversionsApiClient::lerEstatisticas()` novo (metadados do Pixel + `/stats`).
  - `MetaConversaoEstatisticaResource` **não** criado (uso único → projeção inline no
    controller). Só `MetaConversaoEventoResource`.
  - Sub-navegação: `TrafegoTabs.vue` (Configuração | Eventos | Diagnóstico) nas 3
    páginas, ativa por `usePage().component`; `PAGE_SCREEN` mapeia as 3 → `trafego`.

- **Prompt 05 ✅** — `/trafego` deixou de ser mock. `TrafegoController` (`index/store/

- **Prompt 05 ✅** — `/trafego` deixou de ser mock. `TrafegoController` (`index/store/
  update/destroy/testarConexao/eventoTeste`), FormRequests + trait
  `ValidatesMetaCampanha`, `crm/Trafego.vue` reescrita com props reais
  (`campanhas`/`kpis`/`finalidades`), contagem do menu (`crmCounts.trafego` =
  campanhas ativas, via `HandleInertiaRequests`). `access_token` nunca volta ao
  front — só `mascararToken()`. `testarConexao` só grava `token_verificado_em`/
  `token_valido` quando o token testado é o salvo. `eventoTeste` cria linha
  `is_teste = true` (`event_id` `teste_<uuid>`) e despacha `EnviarEventoConversaoMeta`
  via `dispatch_sync`. `destroy` com histórico de eventos → `ativo = false` em vez de
  apagar. As telas `/trafego/eventos` e `/trafego/diagnostico` (seção 9) ficam para
  prompts seguintes.

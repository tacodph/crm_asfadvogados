# Prompt 08 — Avaliação técnica da API de telefonia Api4com (spike)

> Independente dos prompts 01–07 (pode rodar em paralelo). Decisão do usuário: **só
> avaliação técnica nesta série** — nenhum código definitivo entra no CRM. O resultado é um
> documento de decisão (go/no-go) com o desenho da integração para uma série futura.

## Objetivo (itens 4 e 6 do cliente)

- **Item 4:** avaliar a integração do CRM com uma API externa de telefonia (em vez de
  estrutura própria de ligações), para que as ligações sejam feitas e, quando tecnicamente
  possível, **registradas no CRM**, mantendo o histórico de contato do lead.
- **Item 6:** validar a documentação e os requisitos para realizar, registrar e acompanhar
  ligações — em especial o **formato de envio dos números** (DDD, código do país e demais
  parâmetros exigidos).

API: Api4com — explorer `https://api.api4com.com/explorer` (redireciona para
`https://developers.api4com.com/`). Base da API: `https://api.api4com.com/api/v1`.

## O que a documentação já diz (lido em 2026-10-07 — **confirmar contra a conta real**)

| Tema | Documentado | A validar |
|---|---|---|
| Autenticação | `POST /users/login` `{email, password}` → `{id: <token>, ttl: 1209600}` (14 dias). Header `Authorization: <token>` **sem** "Bearer". Token permanente: `POST /users/accessTokens` `{"ttl": -1}`. Troca de senha invalida tokens. Login limitado a 10/min. | Um token por usuário (rate limit é por usuário) ou um token de serviço? |
| Ramais | `GET /extensions` → `{id, ramal, bina, first_name, last_name, email_address, gravar_audio}`. Docs recomendam mapear usuário do CRM → ramal. | `gravar_audio` está ligado nos ramais da ASF? Qual BINA sai? |
| Originar ligação (click-to-call) | `POST /calls` `{extension, caller, called, metadata}`; resposta `{id}`. A API só responde **depois** que a ligação é estabelecida (o ramal toca primeiro). Erros: 404 `EXTENSION_NOT_FOUND`, 422 número inválido, 424 não completou, 429 (15/min por ramal). Desligar: `POST /calls/{id}/hangup`. `/dialer` é obsoleto. | Tempo real até a resposta (define timeout do HTTP client e se precisa ser Job). O ramal precisa estar logado num softphone/webphone? |
| **Formato do número** | `called` em E.164: `+55` + DDD + número, ex. `+5548988669122` (celular) / `+554833328530` (fixo). | Aceita sem `+`? Sem `55`? Com `0` + DDD? Com máscara? Celular sem o 9? Número sem DDD → erro 422? |
| Registro (webhook) | `PATCH /integrations` `{gateway, webhook: true, webhookConstraint, metadata: {webhookUrl, webhookVersion, webhookTypes: ["channel-hangup"]}}`. Eventos `channel-answer` e `channel-hangup`. Só entrega ligações cujo `metadata` casa com `webhookConstraint` → enviar sempre `metadata.gateway`. Payload hangup: `id`, `direction`, `caller`, `called` (**volta em formato nacional `0DDDnúmero`**), `startedAt`, `answeredAt`, `endedAt`, `duration` (s), `hangupCause`, `recordUrl`, `metadata`. | Guias usam `webhookVersion: "v1.4"`, a referência diz que só `1.8` é suportada. `webhookConstraint` aninhado em `metadata` (guia) × plano (referência). Payload de `channel-answer` não documentado. **Sem assinatura/segredo no webhook** e sem política de retry documentada. Fuso de `startedAt`. |
| Histórico/CDR | `GET /calls?page=1&filter=<LoopBack JSON>` → `data[]` com `id, started_at, ended_at, from, to, duration, hangup_cause, record_url, minute_price, call_price, metadata` + `meta` de paginação. Preço chega minutos depois. | Dá para filtrar por `metadata` ou por período? (serve de reconciliação se o webhook falhar). |
| Gravação | Só via `recordUrl`/`record_url` (MP3 em `listener.api4com.com/files/listen/<id>.mp3`). | URL exige autenticação? Expira? Pode ser exibida/streamada no CRM? LGPD: base legal/aviso de gravação. |
| Ligações recebidas | Não coberto no material lido. | Webhook entrega `direction: inbound`? Dá para casar com o contato pelo número? |

## Contexto do CRM (para o desenho proposto)

- Telefone do contato: coluna única `contatos.telefone`, **só dígitos, DDD + número, sem
  55** (`App\Actions\Crm\NormalizeContatoIdentifiers::telefone()`), validação só
  `max:20`. Factory gera números mascarados (inconsistente com produção). Não há coluna de
  celular separada.
- Histórico do lead: `historicos_negociacao` (`tipo` já aceita `call`, `titulo`, `descricao`,
  `autor`, `ocorrido_em`) via `App\Actions\Crm\RegistrarHistoricoNegociacao`.
- Tarefas: `TarefaNegociacao` com `tipo = ligacao`/`follow_up` (prompt 01) — uma ligação
  completada pode concluir o follow-up do dia.
- Padrões já existentes para integração externa: credencial cifrada `App\Casts\SegredoMeta`
  + `App\Models\MetaConversaoConfig` (tela, máscara, "deixe em branco para manter"), Job no
  estilo `App\Jobs\EnviarEventoConversaoMeta` (tries/backoff/log), canal de log dedicado
  `daily`/14 dias, log append-only como `meta_conversao_eventos`, endpoint público com token
  como `POST /api/trafego/leads` (`CapturarLeadTrafegoController`).

## Passos do spike

1. **Pré-requisito com o cliente:** conta Api4com de teste/homologação, usuário com acesso à
   API e pelo menos um ramal de teste com softphone/webphone ativo, e um número de destino
   próprio (celular da equipe) para as ligações de teste. **Nunca ligar para leads reais
   durante o spike.** Credenciais ficam no `.env` local (`API4COM_TOKEN`), nunca no repo.
2. Executar as chamadas com `curl`/HTTPie (ou um script descartável em `storage/` fora do
   git) e registrar request/response (sem token) no documento:
   1. login + criação de token permanente; `GET /users/me`;
   2. `GET /extensions` — anotar ramais, BINA, `gravar_audio`;
   3. **matriz de formatos** em `POST /calls` para o mesmo celular e um fixo:
      `+55DDDNUMERO`, `55DDDNUMERO`, `DDDNUMERO`, `0DDDNUMERO`, `(DD) 9XXXX-XXXX`, celular sem
      o 9, número sem DDD — anotar status/erro de cada um;
   4. tempo até a resposta do `POST /calls` (ramal atende / não atende / recusa);
   5. `POST /calls/{id}/hangup`;
   6. registrar webhook apontando para um receptor de teste (ex.: túnel `herd share`/ngrok
      para uma rota local descartável que só loga o corpo) — testar `webhookVersion` `v1.4`
      e `1.8`, e os dois formatos de `webhookConstraint`; capturar payloads reais de
      `channel-answer` e `channel-hangup` (atendida, não atendida, ocupado);
   7. `GET /calls` com filtro por período e por `metadata` — confirmar reconciliação;
   8. abrir `recordUrl` sem autenticação, com o header `Authorization` e após 24 h;
   9. ligação recebida no ramal (se o plano permitir) — ver se gera webhook `inbound`.
3. Verificar no painel/contrato: custo por minuto, limite de ramais, retenção de gravações,
   SLA, IPs de origem do webhook (para allowlist, já que não há assinatura).

## Entregável

Criar `documents/pontos_lapidacao/avaliacao_api4com.md` com:
1. **Resultado de cada item** da tabela acima (confirmado / divergente / não suportado), com
   evidência (status HTTP, trecho de payload sem dados pessoais).
2. **Regra de formatação de número** final, com a função proposta (pseudocódigo) de
   `contatos.telefone` (dígitos) → formato aceito pela API, e a inversa para casar o `called`
   do webhook (`0DDDNUMERO`) com o contato. Casos: 10 dígitos (fixo), 11 dígitos (celular),
   já com 55, inválidos (bloquear botão "Ligar" com aviso "telefone sem DDD").
3. **Desenho proposto da integração** (não implementar), em formato de série de prompts
   futura:
   - config por tenant `telefonia_configs` (token cifrado com `SegredoMeta`, gateway, URL e
     segredo do webhook) + tela no padrão `/trafego`;
   - mapa usuário → ramal (`users.api4com_ramal` ou tabela própria);
   - botão **Ligar** no drawer/Hoje/Atividades → Job `IniciarLigacaoApi4com` (por causa do
     tempo de resposta), `metadata = {gateway, tenantId, negociacaoId, contatoId, userId}`;
   - tabela `ligacoes` (append-only: `api4com_call_id` único, negociação, contato, usuário,
     direção, início/atendida/fim, duração, causa, `record_url`, custo) +
     `historicos_negociacao` tipo `call` ("Ligação de 3min12s — atendida", link da gravação);
   - webhook `POST /api/telefonia/api4com/webhook/{tokenSecreto}` (segredo na URL, já que a
     API não assina), idempotente pelo `id` da ligação, tenant resolvido pelo `metadata`;
   - reconciliação agendada via `GET /calls` para webhooks perdidos;
   - opção de concluir automaticamente o follow-up/ligação do dia quando a ligação for
     atendida (ou só sugerir — decidir com o cliente);
   - LGPD: aviso de gravação, retenção, quem pode ouvir.
4. **Riscos e pendências** (webhook sem assinatura, divergências da doc, rate limit 15/min
   por ramal, custo).
5. **Recomendação go/no-go** e estimativa de tamanho da série de implementação.

## Fora de escopo
Qualquer migration, model, rota ou tela no CRM. Ligar para leads reais. Commitar credenciais.

## Pronto quando
`avaliacao_api4com.md` existe com todos os itens da tabela respondidos (ou marcados como
"não foi possível validar" com o motivo), a regra de formatação de número está definida e a
recomendação go/no-go está registrada; o README desta pasta aponta para o documento.

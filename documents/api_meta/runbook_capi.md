# Runbook — API de Conversões da Meta (CAPI)

Operação do pipeline server-side de eventos para a Meta. Referência de arquitetura:
[`arquitetura_capi.md`](arquitetura_capi.md). Log dedicado: `storage/logs/meta-capi-*.log`
(canal `meta-capi`, 14 dias, **sem token/IP/UA**).

## Mapa rápido

| Sintoma | Seção |
|---|---|
| Eventos parando com erro `190` / `OAuthException` | [Token expirado](#token-expirado) |
| Muitos `descartado` / `sem_consentimento` | [Consentimento](#pico-de-descartados-sem-consentimento) |
| HTTP 200 mas `events_received = 0` | [Evento não contabilizado](#events_received--0-com-http-200) |
| Eventos dobrados no Events Manager | [Deduplicação](#deduplicacao-nao-funciona) |
| Nada sai, fila crescendo | [Fila parada](#fila-parada) |
| Rotação da chave de criptografia | [Rotação de chave](#rotacao-da-chave-de-criptografia) |

## Comandos operacionais

```bash
php artisan meta:capi-reprocessar --status=erro --campanha=<slug> --force   # recoloca na fila
php artisan meta:capi-reprocessar --desde=2026-09-01 --status=erro          # reprocessar um dia
php artisan meta:sincronizar-estatisticas --campanha=<slug>                 # snapshot do Pixel agora
php artisan meta:capi-expurgar-payloads                                     # LGPD (semanal, seg 04:00)
php artisan meta:recriptografar-tokens --chave-antiga=<KEY> --force         # rotação de chave
php artisan meta:campanha --tenant=<slug> --slug=<slug> --testar            # cadastro sem UI
php artisan queue:work --queue=meta-capi                                    # worker da fila
```

Agendados (`routes/console.php`, `->withoutOverlapping()->onOneServer()`):
`meta:sincronizar-estatisticas` diário 05:30 · `meta:capi-expurgar-payloads` semanal seg 04:00.

---

## Token expirado

Erro `190` (`OAuthException`, "session has expired" / "Error validating access token").
Os eventos afetados ficam `status = erro` e param de ser reenviados sozinhos (erro
permanente, não retentável).

1. **Events Manager** → Dataset do Pixel → **Configurações** → **Gerar token de acesso**
   (token de usuário de sistema; use um usuário de sistema *admin* com a permissão do
   dataset). Copie o token (`EAA...`).
2. CRM → **/trafego** → selecione a campanha → cole no campo **Access token** → **Salvar**.
3. **Testar conexão** — deve mostrar o nome do Pixel e `token_valido = true`.
4. Reprocessar o acúmulo:
   ```bash
   php artisan meta:capi-reprocessar --status=erro --campanha=<slug> --force
   ```
5. Confira em **/trafego/eventos** (filtro status = enviado) e no `meta-capi.log`.

## Pico de descartados / sem_consentimento

`/trafego/diagnostico` mostra "Descartados" alto e `/trafego/eventos` lista
`motivo_descarte = sem_consentimento`.

- Confirme a **finalidade de consentimento** ligada à campanha (`/trafego`, campo
  "Finalidade de consentimento") — tem que ser a finalidade real de *marketing* do
  tenant (`FinalidadeConsentimento.slug`), não um placeholder.
- O gate é **fail-closed**: sem finalidade configurada, **todo** evento é descartado.
- Valide o fluxo de captação: o contato precisa de um `ConsentimentoContato` com
  `finalidade.slug` = o configurado **e** `statusConsentimento.slug = concedido`.
- Descarte por consentimento **não** é reenviável (`meta:capi-reprocessar` só age em
  `status = erro`) — é preciso corrigir o consentimento e gerar um novo evento.

## events_received = 0 com HTTP 200

O payload foi aceito mas nenhum evento válido foi contabilizado. O evento fica
`status = erro` (a regra é `ok = successful() && events_received >= 1`).

- Inspecione `response_body.messages` no drawer de **/trafego/eventos**.
- Causas comuns: `event_time` fora da janela de 7 dias da Meta; `user_data` sem nenhum
  identificador utilizável; `action_source` incompatível.
- O proxy EMQ em `/trafego/diagnostico` mostra a cobertura de `em`/`ph`/`fbp·fbc`/`ip+ua`
  dos eventos enviados — se estiver tudo baixo, o problema é a captação de dados na origem.

## Deduplicação não funciona

Eventos aparecem em dobro no Events Manager (uma vez pelo Pixel do site, outra pela CAPI).

- O Pixel do navegador tem que enviar `eventID` **idêntico** ao `meta_event_id` gravado na
  negociação (é o mesmo valor que a CAPI usa como `event_id`).
- `event_name` tem que ser **exatamente** igual nos dois lados (ex.: `Lead`).
- A negociação com Pixel de origem guarda `meta_event_id`; `/trafego/diagnostico` estima
  "Dedup. (est.)" contando eventos enviados cuja negociação tem esse campo preenchido.

## Fila parada

Eventos ficam `pendente`, nada chega na Meta.

```bash
php artisan queue:work --queue=meta-capi          # subir worker
php artisan queue:failed                          # ver jobs mortos
php artisan queue:retry all                       # reprocessar failed_jobs
```

- O card **Saúde** em `/trafego` mostra `fila meta-capi: N pendente(s)` e a idade do
  último envio por campanha.
- O Job tem `WithoutOverlapping` por evento e `retryUntil(+12h)` com backoff
  `[60, 300, 900, 3600, 10800]`. Depois disso, `failed()` marca `status = erro` →
  use `meta:capi-reprocessar`.

## Rotação da chave de criptografia

A `META_CAPI_ENCRYPTION_KEY` cifra os tokens em repouso (independente do `APP_KEY`).
Para trocar **sem inutilizar os tokens**:

1. Guarde a chave atual (vira `--chave-antiga`).
2. Gere a nova: `php artisan meta:gerar-chave --force` (grava no `.env`).
3. Dry-run: `php artisan meta:recriptografar-tokens --chave-antiga=<KEY_ANTIGA>`.
4. Aplicar: `php artisan meta:recriptografar-tokens --chave-antiga=<KEY_ANTIGA> --force`
   (transação por tenant; `token_verificado_em`/`token_valido` preservados; nunca imprime
   tokens).
5. `/trafego` → **Testar conexão** em cada campanha.

Perder a chave antiga **antes** de rotacionar ⇒ tokens irrecuperáveis (recadastrar em
`/trafego`).

## Evento de teste

`/trafego` → campanha → campo **Test event code** (pegue em Events Manager → **Testar
eventos**) → **Enviar evento de teste**. O evento vai com `is_teste = true` e
`event_id = teste_<uuid>`, aparece na aba **Testar eventos** do Events Manager quase
imediatamente e some do relatório normal.

## Expurgo LGPD

`meta:capi-expurgar-payloads` (semanal) zera `request_payload`/`response_body` de eventos
`enviado` mais velhos que `meta.capi.retencao_dias` (default 180). Preserva `fbtrace_id`,
`status`, `http_status`, `events_received` — o log de auditoria continua íntegro, só o
conteúdo (já hasheado) sai.

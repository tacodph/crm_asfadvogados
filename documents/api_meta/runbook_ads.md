# Runbook — API de Marketing da Meta (Meta Ads)

Operação da leitura de estrutura e *insights* do Meta Ads e da atribuição
`Negociacao` ↔ anúncio. Referência de arquitetura: [`arquitetura_ads.md`](arquitetura_ads.md).
Log dedicado: `storage/logs/meta-ads-*.log` (canal `meta-ads`, 14 dias, **sem token**).

## Mapa rápido

| Sintoma | Seção |
|---|---|
| Sync parando com `OAuthException` (190 / 200 / 10) | [Token sem `ads_read`](#token-sem-ads_read--expirado) |
| Sync fica `parcial`, `X-Business-Use-Case-Usage` alto | [Rate limit](#rate-limit) |
| `spend` / `resultados` de um dia mudaram depois | [Janela de atribuição](#numeros-da-meta-mudando-depois) |
| Lead com `meta_atribuicao_origem` nulo | [Lead sem atribuição](#lead-sem-atribuicao) |
| CPL CRM muito maior que CPL Meta | [Reconciliação](#cpl-crm--cpl-meta) |
| Conta com `account_status != 1` | [Conta desativada](#conta-de-anuncio-desativada) |
| Valores em moeda estranha | [Moeda](#moeda-diferente-de-brl) |

## Comandos operacionais

```bash
php artisan meta:ads-conta --tenant=<slug> --conta=<id> --nome="X"                  # cadastro sem UI
php artisan meta:ads-sincronizar --conta=<id> --tipo=tudo --sync                    # sync inline agora
php artisan meta:ads-sincronizar --dias=7                                           # janela custom (enfileira)
php artisan meta:ads-backfill-insights --conta=<id> --desde=2026-06-01 --ate=2026-06-30 --force
php artisan meta:ads-reatribuir --desde=2026-06-01 --forcar                         # após sync que trouxe anúncios novos
php artisan meta:ads-expurgar-brutos                                                # retenção (semanal seg 04:30)
php artisan queue:work --queue=meta-ads                                             # worker da fila
```

Agendados (`routes/console.php`, `->withoutOverlapping()->onOneServer()`):
`meta:ads-sincronizar` diário 06:00 · `meta:ads-expurgar-brutos` semanal seg 04:30.

---

## Token sem `ads_read` / expirado

Erro `190` (`OAuthException`), `200` ("permissions error"), `10` ("application does not
have permission"). A conta fica com `token_valido = false` e a sync a **pula**
(`meta:ads-sincronizar` mostra "token inválido — pulada").

1. **Business Manager** → **Configurações do negócio** → **Usuários** → **Usuários de
   sistema** → crie/selecione um usuário de sistema **admin**.
2. **Atribuir ativos** → adicione a **conta de anúncio** com controle total.
3. **Gerar novo token** → marque **`ads_read`** (e **`ads_management`** se quiser pausar
   conjuntos no futuro). Copie o token (`EAA…`).
4. No app da Meta: garanta **Advanced Access** a `ads_read` (Revisão do app) para uso em
   produção fora dos papéis de desenvolvimento.
5. CRM → **/trafego → Investimento** → **Editar** a conta → cole o token → **Salvar**.
   O "Testar" deve mostrar `BRL · ads_read`.
6. Recarregue: `php artisan meta:ads-sincronizar --conta=<id> --sync`.

## Rate limit

Header `X-Business-Use-Case-Usage` (por conta) ou `error.code` ∈ {4, 17, 32, 613, 80000}.

- O `MarketingApiClient` **para a paginação** quando o uso passa de
  `config('meta.ads.rate_limit_teto_pct', 85)` ou quando a Meta manda
  `estimated_time_to_regain_access`. A rodada fecha como **`parcial`** (não é erro).
- O Job re-agenda: estrutura recua ~15 min; *insights* parte a janela ao meio.
- A sync diária (06:00) completa o que ficou. Para forçar fora de pico:
  `php artisan meta:ads-backfill-insights --conta=<id> --desde=<...> --ate=<...> --force`
  de madrugada (janela grande usa o **relatório assíncrono** da Meta automaticamente).

## Números da Meta mudando depois

`spend` / `resultados` de um dia sobem 2–3 dias depois — **esperado**. As janelas de
atribuição da Meta (7d de clique / 1d de visualização) fazem os números assentarem ao
longo de ~28 dias. Por isso a sync diária **re-puxa os últimos
`config('meta.ads.reprocessar_dias', 28)` dias** (`updateOrCreate` idempotente). Para
recarregar um período específico: `meta:ads-backfill-insights`.

## Lead sem atribuição

`negociacoes.meta_atribuicao_origem IS NULL`.

- **Leads orgânicos ficam sem atribuição** — isso é correto.
- Para leads de anúncio: o link do anúncio precisa passar `utm_source=facebook|instagram`
  e `utm_content={{ad.id}}` (ou `utm_campaign={{campaign.id}}`) nos parâmetros de URL.
  Configuração no Gerenciador de Anúncios (cliente).
- Depois de uma sync de estrutura que trouxe anúncios novos, rode
  `php artisan meta:ads-reatribuir --desde=<Y-m-d>` para preencher as lacunas
  (sem `--forcar` só toca quem tem `meta_ad_id` nulo).
- Mapeamento manual de último recurso: `config('meta.ads.mapa_campanha')` liga o slug da
  campanha do CRM a um `meta_campaign_id`.

## CPL CRM ≫ CPL Meta

A Meta conta como "lead" ações que o CRM não recebeu (formulário abandonado após o
submit, integração de lead ad desligada, clique sem conversão real). A coluna **Leads
CRM** da aba Investimento e o `MetricasInvestimentoMeta` usam **as negociações do CRM**
como fonte de verdade de negócio; "Leads Meta" é só referência. Um ícone ⚠ aparece na
tabela quando a divergência passa de 40%.

## Conta de anúncio desativada

`account_status` na conta ≠ `1` (ex.: `2` = desativada, `3` = fechada por não-pagamento).
A aba Investimento mostra o status ao vivo (deferred). A sync registra `parcial`/`erro`
em `meta_ads_sync_execucoes` com a mensagem da Meta. Resolver a pendência de
faturamento/política no Business Manager; a sync volta sozinha na próxima rodada.

## Moeda diferente de BRL

`spend` vem na moeda da conta. Os valores são gravados **na moeda da conta** (centavos) e
`meta_ads_contas.moeda` é usada na formatação da UI. **Não há conversão** — se houver
contas em moedas diferentes, os KPIs "todas as contas" misturam moedas; filtre por conta.

## Retenção / expurgo

`meta:ads-expurgar-brutos` (semanal) zera `bruto`/`acoes` de `meta_ads_insights_diarios`
e `bruto` da estrutura mais velhos que `config('meta.capi.retencao_dias')` (default 180,
mesma config da CAPI). Os **agregados numéricos** (`investimento_centavos`, `resultados`,
etc.) e a atribuição das negociações permanecem.

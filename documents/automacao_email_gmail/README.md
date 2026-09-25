# Captação de leads por e-mail (Gmail) — série de prompts

Terceiro canal de entrada de leads no CRM, ao lado do endpoint público de tráfego
(`POST /api/trafego/leads`, ver `documents/api_meta/captacao_leads_site.md`) e da
sincronização da Meta Ads API (`documents/api_meta/arquitetura_ads.md`). Aqui a fonte é uma
caixa de e-mail corporativa do Google Workspace que recebe, por mensagem, uma tabela HTML
fixa com: Data do Cadastro, Nome, Telefone, E-mail, Valor da Dívida, PF ou PJ?, Campanha,
Conjunto, Anúncio.

Cada prompt abaixo é autocontido — uma sessão de desenvolvimento pode pegar um prompt,
implementar exatamente o que ele descreve contra o repo real, rodar os testes que ele pede,
e seguir para o próximo. Execute na ordem.

| Prompt | Entrega |
|---|---|
| [01_estrategia_captura_imap.md](01_estrategia_captura_imap.md) | Pacotes, migrations, tela `/trafego → E-mail` (config da caixa + funil de destino), comando `leads:email-capturar`, agendamento, canal de log. |
| [02_parser_html_domcrawler.md](02_parser_html_domcrawler.md) | DTO `LeadEmailDados`, `ParserLeadEmailHtml` (Symfony DomCrawler), exceção de parsing, testes unitários. |
| [03_job_action_persistencia.md](03_job_action_persistencia.md) | Job `ProcessarLeadEmailRecebido`, Action `RegistrarLeadEmail` (Contato/Empresa/Negociação, abre no funil da conta), testes de feature. |
| [04_hardening_observabilidade.md](04_hardening_observabilidade.md) | Tratamento de erro permanente vs retry, comando de reprocessamento manual, `runbook.md`. |

## Decisões já travadas (não reabrir sem motivo novo)

- **IMAP, não API do Gmail.** `webklex/laravel-imap` + agendamento a cada poucos minutos.
  A API oficial do Gmail exigiria OAuth2 + Pub/Sub para push real — desproporcional ao
  problema. Ver justificativa completa no prompt 01.
- **Configuração, funil de destino e finalidade de consentimento: tela `/trafego → E-mail`,
  DB-first (não `.env`).** Mesma decisão já tomada para a Meta CAPI (`arquitetura_capi.md`,
  §7): credenciais IMAP (senha cifrada via `App\Casts\SegredoMeta`, reaproveitando a chave
  da CAPI), o **funil para onde o lead vai** e a **finalidade de consentimento LGPD** sob a
  qual ele é registrado ficam numa tabela (`email_leads_contas`), cadastrados pela equipe
  de tráfego numa tela — não hardcoded no código. Isso já nasce multi-tenant (cada linha é
  de um tenant via `BelongsToTenant`), então o MVP de hoje (um cliente, ASF) vira SaaS sem
  nenhuma mudança de código — só a equipe de tráfego de cada novo cliente cadastra sua
  própria conta e escolhe seu próprio funil pela mesma tela.
- **PJ sem CNPJ.** O e-mail não traz CPF/CNPJ, só "PF ou PJ?". Por isso a Action **não cria
  `Empresa`** — só marca `tipo_pessoa_id` no `Contato`, deixando `empresa_id` nulo. Se um
  dia o formulário passar a coletar CNPJ, revisar `03_job_action_persistencia.md`.
- **Sempre nova `Negociacao`, dedup só no `Contato`.** Mesmo padrão do endpoint de tráfego
  (`CapturarLeadTrafegoController`): um e-mail repetido do mesmo contato não deve
  atualizar um negócio existente, e sim abrir um novo — o dado de negócio (valor da
  dívida, campanha) é do lead novo, não do histórico.
- **Idempotência pelo `Message-ID` do e-mail**, não por "já li no IMAP". Tabela
  `email_leads_processados` é a fonte de verdade — mesmo padrão de log append-only que a
  série `api_meta` usa em `meta_conversao_eventos`.

## Reuso obrigatório (não reinventar)

- `App\Actions\Crm\FindContatoDuplicatas` — dedup de contato por e-mail/telefone.
- `App\Http\Controllers\CapturarLeadTrafegoController` — desenho de referência do fluxo
  contato → consentimento LGPD → negociação (copiar a lógica para a Action de e-mail, não
  para o controller).
- `App\Casts\SegredoMeta` + `App\Models\MetaConversaoConfig` — padrão de credencial cifrada
  em repouso + máscara na UI + "deixe em branco para manter" no update, reaproveitado tal
  qual para `EmailLeadConta`.
- `App\Http\Controllers\TrafegoController`/`TrafegoInvestimentoController` +
  `resources/js/components/crm/TrafegoTabs.vue` — desenho de referência da tela/CRUD e da
  navegação por abas em `/trafego`, reaproveitado para a aba "E-mail".
- `App\Support\Tenancy\CurrentTenant::runAs()` — resolução de tenant fora de request.
- `App\Jobs\EnviarEventoConversaoMeta` — estilo de Job (tries/backoff/WithoutOverlapping/log).
- Canal de log dedicado (`config/logging.php`, padrão `daily`/14 dias) e agendamento
  (`routes/console.php`, `withoutOverlapping()->onOneServer()`) como em `meta-capi`/`meta-ads`.

## Estado da implementação (2026-09-21)

**Série executada por completo (prompts 01–04) contra o repo real.** Desvios do desenho
original desta pasta, descobertos ao implementar:
- `EmailLeadConta` ganhou `finalidade_consentimento_slug` (obrigatório) além de `funil_id`
  — não estava no prompt 01 original, mas é indispensável pra `registrarConsentimento()`
  funcionar (mesmo problema que `MetaConversaoConfig` já resolve com o mesmo padrão).
- `RegistrarLeadEmail::__invoke()` recebe `EmailLeadConta $conta` inteira (não um `Funil`
  avulso) — ela carrega funil e finalidade juntos, evita dois parâmetros correlacionados.
- Commands usam os atributos `#[Signature]`/`#[Description]` (estilo Laravel 13 do resto do
  repo), não `protected $signature`/`$description`.
- Jobs usam `Illuminate\Foundation\Queue\Queueable` (trait "tudo em um" — já inclui
  `Dispatchable`/`InteractsWithQueue`/`SerializesModels`), não `Illuminate\Bus\Queueable` +
  `InteractsWithQueue` separados.
- Parser: `Illuminate\Support\Str::ascii()` no lugar de `iconv(...TRANSLIT)` (o iconv
  depende de locale/glibc do SO e dava resultado errado no Windows); datas
  `CarbonImmutable::createFromFormat('d/m/Y H:i', ...)` explícito antes de tentar
  `::parse()` (formato brasileiro `dd/mm/aaaa` é ambíguo pro parser genérico).
- Migration `email_leads_processados.negociacao_id`: `constrained('negociacoes')` explícito
  — o Eloquent/Schema builder pluraliza `negociacao_id` em inglês (`negociacaos`, errado).
- Testes: 20 novos (`tests/Unit/ParserLeadEmailHtmlTest.php`,
  `tests/Feature/{ProcessarLeadEmailRecebidoTest,ReprocessarLeadEmailTest,
  TrafegoEmailTest}.php`), suíte completa 433 verdes (1 falha pré-existente e não
  relacionada em `WelcomeOnePageTest`), phpstan e `vue-tsc` sem erro novo, `npm run build` ok.
- **Não testado**: conexão IMAP real (webklex/laravel-imap) contra uma caixa Gmail de
  verdade — os testes mockam `ClientManager`/`ConnectionFailedException`. Antes de ativar o
  agendamento em produção, cadastrar a conta pela tela e usar "Testar conexão" com a senha
  de app real.

# Captação de leads pelo site — `POST /api/trafego/leads`

Endpoint público que recebe um lead do site/landing/link-tree e cria contato +
consentimento + negociação no CRM. O `NegociacaoObserver` então dispara o evento
`Lead` da API de Conversões (com deduplicação pelo `event_id` do Pixel) e a
atribuição de anúncio (`AtribuirNegociacaoAnuncioMeta`).

## Autenticação

Segredo por tenant, gerado em **/trafego → aba Configuração → "Captação de leads
pelo site"** (botão *Gerar token*) ou por CLI:

```
php artisan meta:trafego-token --tenant=<slug>
php artisan meta:trafego-token --tenant=<slug> --rotacionar
```

O valor cru é mostrado **uma vez**. No banco fica só o hash SHA-256. Enviar em
todo request:

```
Authorization: Bearer asf_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

(ou `X-Trafego-Token: asf_...`). Sem token válido → `401`. Rate limit: 60/min por IP.

## Corpo (JSON)

| Campo | Obrigatório | Observação |
|---|---|---|
| `nome` | sim | |
| `email` **ou** `telefone` | sim (um dos dois) | usados para deduplicar o contato |
| `finalidade` | sim | slug de uma `FinalidadeConsentimento` do tenant (ex.: `contato-comercial`) — a finalidade que a caixa de opt-in do formulário cobre |
| `consentimento` | sim | `true` — o visitante marcou o aceite LGPD |
| `cpf`, `cidade`, `uf` (2 letras), `cep` | não | `cep` e `uf` alimentam o Advanced Matching (`zp`, `st`) |
| `assunto` | não | default "Lead do site" |
| `valor` | não | valor estimado do caso, em reais |
| `funil_slug`, `canal` | não | default: primeiro funil / canal `site` |
| `meta_event_id` | não | **mesmo `eventID` usado no `fbq('track','Lead', …)`** → dedup navegador × CAPI |
| `meta_fbp`, `meta_fbc` | não | cookies `_fbp` / `_fbc` |
| `meta_event_source_url` | não | URL da página do formulário |
| `client_ip_address`, `client_user_agent` | não | **só no modo servidor-a-servidor** (ver abaixo); sem eles, o CRM usa o IP/UA da requisição |
| `utm_source`/`utm_medium`/`utm_campaign`/`utm_content`/`utm_term`, `fbclid` | não | atribuição. `utm_content = {{ad.id}}` casa direto com o anúncio |

Resposta `201`: `{ "ok": true, "negociacao_id": 123, "contato_id": 45 }`.
Erros de validação: `422` com `{ "message", "errors" }`.

## Dois modos de integração

### A. Servidor-a-servidor (recomendado)

O backend da landing recebe o POST do formulário, lê os cookies `_fbp`/`_fbc`
(mesma origem) e o `User-Agent`/IP do visitante, e repassa tudo — **incluindo
`client_ip_address` e `client_user_agent`** — para o endpoint. O token fica no
servidor, nunca no HTML.

### B. Direto do navegador

O JS da página faz `fetch` para o endpoint. Simples, mas **expõe o token no
código-fonte** — use um token dedicado e rotacione se vazar. CORS já liberado
para `/api/*`.

## Snippet do Pixel (mesma `eventID` nos dois lados)

```html
<script>
  // 1) um id por sessão de lead
  const eventId = crypto.randomUUID();

  // 2) evento do Pixel do navegador
  fbq('track', 'Lead', {}, { eventID: eventId });

  // 3) ao enviar o formulário, manda o lead + parâmetros do Pixel
  async function enviarLead(dados) {
    await fetch('/meu-backend/lead', {           // modo A: seu backend repassa
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        ...dados,                                 // nome, email, telefone, finalidade, consentimento
        meta_event_id: eventId,
        meta_fbp: document.cookie.match(/_fbp=([^;]+)/)?.[1],
        meta_fbc: document.cookie.match(/_fbc=([^;]+)/)?.[1],
        meta_event_source_url: location.href,
        utm_source: new URLSearchParams(location.search).get('utm_source'),
        utm_campaign: new URLSearchParams(location.search).get('utm_campaign'),
        utm_content: new URLSearchParams(location.search).get('utm_content'),
        fbclid: new URLSearchParams(location.search).get('fbclid'),
      }),
    });
  }
</script>
```

Com `event_name` = `Lead` e `eventID` == `meta_event_id`, a Meta descarta o
duplicado entre o Pixel e a CAPI.

## Verificação

- `php artisan meta:trafego-token` → pega o token.
- `curl -X POST <endpoint> -H "Authorization: Bearer <token>" -H "Content-Type: application/json"
  -d '{"nome":"Teste","email":"t@t.com","finalidade":"contato-comercial","consentimento":true}'`
  → `201`; o lead aparece no funil e em **/trafego → Eventos** como `Lead` (não `descartado`).

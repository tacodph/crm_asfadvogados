<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API de Conversões da Meta (CAPI)
    |--------------------------------------------------------------------------
    |
    | Config de aplicação da integração server-side com a Meta. As credenciais
    | de cada campanha (pixel_id + access_token) NÃO ficam aqui: são cadastradas
    | e criptografadas no banco (tabela meta_conversao_configs), via tela
    | /trafego ou `php artisan meta:campanha`. Ver documents/api_meta/arquitetura_capi.md.
    |
    | A seção `ads` (abaixo) é a leitura do Meta Ads (Marketing API): estrutura de
    | campanhas/conjuntos/anúncios e insights de investimento. As contas de anúncio
    | (ad_account_id + token ads_read) ficam cifradas em meta_ads_contas, pela aba
    | /trafego → Investimento ou `php artisan meta:ads-conta`. O token usa o MESMO
    | cast/chave da CAPI (META_CAPI_ENCRYPTION_KEY). Ver arquitetura_ads.md.
    |
    */

    'capi' => [

        'api_version' => env('META_CAPI_API_VERSION', 'v21.0'),

        'base_url' => 'https://graph.facebook.com',

        'default_action_source' => env('META_CAPI_DEFAULT_ACTION_SOURCE', 'system_generated'),

        'instagram_url' => env('META_CAPI_INSTAGRAM_URL'),

        'queue' => env('META_CAPI_QUEUE', 'meta-capi'),

        'retencao_dias' => (int) env('META_CAPI_RETENCAO_DIAS', 180),

        'test_event_code' => env('META_CAPI_TEST_EVENT_CODE'),

        /*
        | Slugs de StatusConsentimento que contam como "consentimento vigente" e
        | liberam o disparo de eventos (gate LGPD em RegistrarEventoConversaoMeta).
        | Precisa bater com o catálogo do tenant (SeedDefaultCatalogsForTenant):
        | o padrão marca opt-in ativo como 'opt-in-registrado' e 'vigente'.
        */
        'slugs_consentimento_valido' => ['opt-in-registrado', 'vigente'],

        /*
        | Chave dedicada usada pelo cast App\Casts\SegredoMeta para cifrar os
        | tokens de campanha em repouso. Independente do APP_KEY: rotacionar o
        | APP_KEY não afeta os tokens. Gere uma por ambiente com
        | `php artisan meta:gerar-chave`. Perder esta chave sem rotacionar antes
        | (`php artisan meta:recriptografar-tokens`) inutiliza todos os tokens.
        */
        'encryption_key' => env('META_CAPI_ENCRYPTION_KEY'),

        /*
        | Mapa opcional funil/canal -> slug da campanha, consumido por
        | App\Support\Meta\ResolverCampanhaConversao. Chave = trecho em minúsculas
        | do nome do funil/canal; valor = slug em meta_conversao_configs. Se vazio,
        | o resolver usa heurística ("bancár*" -> bancario, "concurso*" -> concurso)
        | e, como último recurso, a única campanha ativa.
        */
        'mapa_campanha' => [
            // 'previdenciário' => 'previdenciario',
        ],

        /*
        | Mudança de etapa da negociação -> evento da Meta. Chave = trecho em
        | minúsculas do nome da etapa; valor = MetaEventName. A negociação criada
        | sempre gera `Lead` (independente deste mapa). Casamento por trecho do
        | nome, primeiro match vence — renomear uma etapa no admin exige revisar
        | este mapa. Os trechos abaixo cobrem as etapas padrão dos funis
        | "B2B consultivo" e "Concursos (PF, volume)".
        */
        'mapa_etapa_evento' => [
            'triagem' => 'Schedule',
            'diagnóstico' => 'Schedule',
            'diagnostico' => 'Schedule',
            'reuni' => 'Schedule',
            'agendad' => 'Schedule',
            'agendou' => 'Schedule',
            'apresentação da solução' => 'SubmitApplication',
            'apresentacao da solucao' => 'SubmitApplication',
            'envio da oferta' => 'SubmitApplication',
            'negociação' => 'SubmitApplication',
            'negociacao' => 'SubmitApplication',
            'proposta' => 'SubmitApplication',
            'minuta' => 'SubmitApplication',
            'fechamento' => 'Purchase',
            'contrato assinado' => 'Purchase',
            'fechado' => 'Purchase',
            'ganho' => 'Purchase',
            'cliente ativo' => 'Purchase',
        ],

    ],

    'ads' => [

        'api_version' => env('META_ADS_API_VERSION', env('META_CAPI_API_VERSION', 'v21.0')),

        'base_url' => 'https://graph.facebook.com',

        'queue' => env('META_ADS_QUEUE', 'meta-ads'),

        // Timeout (s) das chamadas à Graph API de leitura.
        'http_timeout' => (int) env('META_ADS_HTTP_TIMEOUT', 15),

        // Insights re-sincronizam esta janela a cada rodada (atribuição assenta ~28d).
        'reprocessar_dias' => (int) env('META_ADS_REPROCESSAR_DIAS', 28),

        // Janela maior que isto usa o relatório assíncrono da Meta.
        'async_dias' => (int) env('META_ADS_ASYNC_DIAS', 32),

        // Recuo quando X-Business-Use-Case-Usage passa deste % de uso.
        'rate_limit_teto_pct' => (int) env('META_ADS_RATE_LIMIT_TETO', 85),

        // Campos padrão do /insights (nível anúncio).
        'insights_fields' => [
            'spend', 'impressions', 'clicks', 'inline_link_clicks', 'reach',
            'frequency', 'cpc', 'cpm', 'ctr', 'actions', 'cost_per_action_type',
        ],

        // action_type que contam como "resultado / lead" (normalização — usar o maior,
        // não somar, quando mais de um coexiste no mesmo dia).
        'acoes_resultado' => [
            'lead',
            'onsite_conversion.lead_grouped',
            'offsite_conversion.fb_pixel_lead',
        ],

        /*
        | Mapa opcional slug-da-campanha-CRM -> meta_campaign_id, usado como último
        | recurso pela atribuição (App\Actions\Meta\AtribuirNegociacaoAnuncioMeta)
        | quando não há lead ad / utm / fbclid.
        */
        'mapa_campanha' => [
            // 'bancario' => '238...',
        ],

    ],

];

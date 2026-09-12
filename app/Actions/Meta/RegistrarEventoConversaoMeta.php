<?php

namespace App\Actions\Meta;

use App\Enums\MetaConversaoEventoStatus;
use App\Enums\MetaEventName;
use App\Jobs\EnviarEventoConversaoMeta;
use App\Models\MetaConversaoConfig;
use App\Models\MetaConversaoEvento;
use App\Models\Negociacao;
use App\Support\Meta\AdvancedMatching;
use App\Support\Meta\CapiPayloadBuilder;
use App\Support\Meta\ElegibilidadeEventoMeta;
use App\Support\Meta\ResolverCampanhaConversao;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Registra um evento de conversão a partir de uma negociação do CRM:
 * resolve a campanha, aplica o gate de consentimento (LGPD), monta o payload
 * com PII já hasheada e enfileira o envio. Idempotente por `event_id`.
 */
class RegistrarEventoConversaoMeta
{
    public function __construct(
        private readonly ResolverCampanhaConversao $resolverCampanha,
        private readonly CapiPayloadBuilder $payloadBuilder,
        private readonly ElegibilidadeEventoMeta $elegibilidade,
    ) {}

    public function __invoke(
        Negociacao $negociacao,
        MetaEventName $evento,
        ?string $slugCampanha = null,
    ): ?MetaConversaoEvento {
        $config = ($this->resolverCampanha)($negociacao, $slugCampanha);

        if ($config === null) {
            // Integração desligada (nenhuma campanha ativa) ⇒ no-op silencioso.
            if (! MetaConversaoConfig::query()->ativas()->exists()) {
                return null;
            }

            return $this->descartar($negociacao, null, $evento, 'campanha_indefinida');
        }

        if (! $config->ativo) {
            return $this->descartar($negociacao, $config, $evento, 'campanha_inativa');
        }

        $eventId = $this->eventId($negociacao, $evento);

        $existente = MetaConversaoEvento::query()
            ->where('meta_conversao_config_id', $config->id)
            ->where('event_id', $eventId)
            ->first();

        if ($existente !== null) {
            if ($existente->status === MetaConversaoEventoStatus::Erro) {
                EnviarEventoConversaoMeta::dispatch($existente)
                    ->onQueue((string) config('meta.capi.queue'));
            }

            return $existente;
        }

        $actionSource = ($negociacao->meta_event_source_url !== null || $negociacao->meta_fbp !== null)
            ? 'website'
            : (string) config('meta.capi.default_action_source', 'system_generated');

        $eventTime = $negociacao->meta_captado_em ?? Carbon::now();

        if (! $this->elegibilidade->temConsentimento($negociacao, $config)) {
            return $this->descartar($negociacao, $config, $evento, 'sem_consentimento', $eventId, $eventTime, $actionSource);
        }

        $payload = $this->montarPayload($negociacao, $config, $evento, $eventId, $eventTime, $actionSource);

        $linha = $this->criarLinha($negociacao, $config, $evento, $eventId, $eventTime, $actionSource, [
            'status' => MetaConversaoEventoStatus::Pendente,
            'request_payload' => $payload,
        ]);

        EnviarEventoConversaoMeta::dispatch($linha)->onQueue((string) config('meta.capi.queue'));

        return $linha;
    }

    private function eventId(Negociacao $negociacao, MetaEventName $evento): string
    {
        $daOrigem = $negociacao->meta_event_id;

        if (is_string($daOrigem) && $daOrigem !== '') {
            return $daOrigem;
        }

        return sprintf('%s_%d', mb_strtolower($evento->value), $negociacao->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function montarPayload(
        Negociacao $negociacao,
        MetaConversaoConfig $config,
        MetaEventName $evento,
        string $eventId,
        DateTimeInterface $eventTime,
        string $actionSource,
    ): array {
        $browser = [];

        if ($actionSource === 'website') {
            $browser = array_filter([
                'fbp' => $negociacao->meta_fbp,
                'fbc' => $negociacao->meta_fbc,
                'client_ip_address' => $negociacao->meta_client_ip,
                'client_user_agent' => $negociacao->meta_client_user_agent,
            ], static fn ($v): bool => is_string($v) && $v !== '');
        }

        $userData = AdvancedMatching::userDataFrom($negociacao->contato, $browser, $negociacao->empresa);

        if ($evento === MetaEventName::Purchase && (float) $negociacao->valor <= 0.0) {
            Log::channel('meta-capi')->warning('meta-capi: Purchase sem valor — value/currency omitidos', [
                'negociacao_id' => $negociacao->id,
                'campanha' => $config->slug,
            ]);
        }

        $customData = $this->payloadBuilder->customDataParaNegociacao(
            $evento === MetaEventName::Purchase ? $negociacao : null,
            $config->nome_campanha,
        );

        return $this->payloadBuilder->build(
            $evento,
            $eventId,
            $eventTime,
            $actionSource,
            $userData,
            $customData,
            $config->origem_url,
        );
    }

    private function descartar(
        Negociacao $negociacao,
        ?MetaConversaoConfig $config,
        MetaEventName $evento,
        string $motivo,
        ?string $eventId = null,
        ?DateTimeInterface $eventTime = null,
        ?string $actionSource = null,
    ): MetaConversaoEvento {
        $eventId ??= $this->eventId($negociacao, $evento);
        $eventTime ??= Carbon::now();
        $actionSource ??= (string) config('meta.capi.default_action_source', 'system_generated');

        return $this->criarLinha($negociacao, $config, $evento, $eventId, $eventTime, $actionSource, [
            'status' => MetaConversaoEventoStatus::Descartado,
            'motivo_descarte' => $motivo,
            'request_payload' => [
                'event_name' => $evento->value,
                'event_id' => $eventId,
                'event_time' => $eventTime->getTimestamp(),
                'action_source' => $actionSource,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function criarLinha(
        Negociacao $negociacao,
        ?MetaConversaoConfig $config,
        MetaEventName $evento,
        string $eventId,
        DateTimeInterface $eventTime,
        string $actionSource,
        array $extra,
    ): MetaConversaoEvento {
        return MetaConversaoEvento::query()->create([
            'meta_conversao_config_id' => $config?->id,
            'negociacao_id' => $negociacao->id,
            'contato_id' => $negociacao->contato_id,
            'event_name' => $evento,
            'event_id' => $eventId,
            'event_time' => $eventTime,
            'action_source' => $actionSource,
            'request_payload' => [],
            ...$extra,
        ]);
    }
}

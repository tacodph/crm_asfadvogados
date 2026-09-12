<?php

namespace App\Http\Controllers;

use App\Enums\MetaConversaoEventoStatus;
use App\Enums\MetaEventName;
use App\Http\Requests\EventoTesteMetaRequest;
use App\Http\Requests\StoreMetaConversaoConfigRequest;
use App\Http\Requests\TestarConexaoMetaRequest;
use App\Http\Requests\UpdateMetaConversaoConfigRequest;
use App\Jobs\EnviarEventoConversaoMeta;
use App\Models\FinalidadeConsentimento;
use App\Models\MetaConversaoConfig;
use App\Models\MetaConversaoEvento;
use App\Support\Meta\AdvancedMatching;
use App\Support\Meta\CapiPayloadBuilder;
use App\Support\Meta\ConversionsApiClient;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD das campanhas da API de Conversões da Meta (tela /trafego): cadastro,
 * edição, teste de conexão com o Pixel e disparo de evento de teste. O
 * `access_token` nunca volta para o front — só a máscara `••••1234`.
 */
class TrafegoController extends Controller
{
    public function index(): Response
    {
        $configs = MetaConversaoConfig::query()
            ->with('atualizadoPor:id,name')
            ->orderBy('nome_campanha')
            ->get();

        $desde = now()->subDays(30);

        $eventos = MetaConversaoEvento::query()
            ->where('created_at', '>=', $desde)
            ->selectRaw('status, motivo_descarte, count(*) as total')
            ->groupBy('status', 'motivo_descarte')
            ->get();

        $contar = fn (callable $filtro): int => (int) $eventos
            ->filter($filtro)
            ->sum('total');

        return Inertia::render('crm/Trafego', [
            'campanhas' => $configs
                ->map(fn (MetaConversaoConfig $config): array => $this->campanhaSegura($config))
                ->values()
                ->all(),
            'kpis' => [
                [
                    'label' => 'Eventos enviados (30d)',
                    'value' => (string) $contar(
                        fn (MetaConversaoEvento $e): bool => $e->status === MetaConversaoEventoStatus::Enviado,
                    ),
                ],
                [
                    'label' => 'Descartados sem consentimento',
                    'value' => (string) $contar(
                        fn (MetaConversaoEvento $e): bool => $e->status === MetaConversaoEventoStatus::Descartado
                            && $e->motivo_descarte === 'sem_consentimento',
                    ),
                ],
                [
                    'label' => 'Eventos com erro (30d)',
                    'value' => (string) $contar(
                        fn (MetaConversaoEvento $e): bool => $e->status === MetaConversaoEventoStatus::Erro,
                    ),
                ],
                [
                    'label' => 'Campanhas ativas',
                    'value' => (string) $configs->where('ativo', true)->count(),
                ],
            ],
            'finalidades' => FinalidadeConsentimento::query()
                ->orderBy('nome')
                ->get(['slug', 'nome'])
                ->map(fn (FinalidadeConsentimento $f): array => [
                    'slug' => $f->slug,
                    'nome' => $f->nome,
                ])
                ->values()
                ->all(),
            'saude' => $this->saude($configs),
            'captacaoSite' => $this->captacaoSite(),
        ]);
    }

    /**
     * Estado do endpoint público de captação de leads pelo site.
     *
     * @return array<string, mixed>
     */
    private function captacaoSite(): array
    {
        $tenant = app(CurrentTenant::class)->get();

        return [
            'endpoint' => url('/api/trafego/leads'),
            'token_mascara' => $tenant?->mascararTokenLeadTrafego() ?? '—',
            'token_gerado_em' => $tenant?->trafego_lead_token_gerado_em?->toIso8601String(),
        ];
    }

    /**
     * (Re)gera o segredo de captação de leads e devolve o valor cru uma vez.
     */
    public function gerarTokenLead(): RedirectResponse
    {
        $tenant = app(CurrentTenant::class)->get();

        abort_if($tenant === null, 403);

        $raw = $tenant->gerarTokenLeadTrafego();

        Inertia::flash('tokenCaptacaoSite', $raw);

        return redirect()->route('trafego.index');
    }

    /**
     * Cartão de saúde operacional do pipeline: erros recentes, fila e atraso.
     *
     * @param  Collection<int, MetaConversaoConfig>  $configs
     * @return array<string, mixed>
     */
    private function saude(Collection $configs): array
    {
        $ultimoEnviadoPorConfig = MetaConversaoEvento::query()
            ->where('status', MetaConversaoEventoStatus::Enviado->value)
            ->selectRaw('meta_conversao_config_id, max(enviado_em) as ultimo')
            ->groupBy('meta_conversao_config_id')
            ->pluck('ultimo', 'meta_conversao_config_id');

        return [
            'erros_24h' => MetaConversaoEvento::query()
                ->where('status', MetaConversaoEventoStatus::Erro->value)
                ->where('updated_at', '>=', now()->subDay())
                ->count(),
            'fila_pendente' => Queue::size((string) config('meta.capi.queue')),
            'campanhas' => $configs->map(fn (MetaConversaoConfig $c): array => [
                'nome' => $c->nome_campanha,
                'ultimo_evento_em' => ($ts = $ultimoEnviadoPorConfig[$c->id] ?? null) !== null
                    ? Carbon::parse($ts)->toIso8601String()
                    : null,
            ])->values()->all(),
        ];
    }

    public function store(
        StoreMetaConversaoConfigRequest $request,
        ConversionsApiClient $client,
    ): RedirectResponse {
        $config = MetaConversaoConfig::query()->create([
            ...$request->validated(),
            'atualizado_por_user_id' => $request->user()?->id,
        ]);

        $this->registrarVerificacaoToken($config, $client);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $this->mensagemSalvarCampanha($config, 'cadastrada'),
        ]);

        return redirect()->route('trafego.index');
    }

    public function update(
        UpdateMetaConversaoConfigRequest $request,
        MetaConversaoConfig $config,
        ConversionsApiClient $client,
    ): RedirectResponse {
        $dados = $request->validated();
        $tokenAlterado = filled($dados['access_token'] ?? null);

        // "Deixe em branco para manter": só troca o token se veio valor novo.
        if (blank($dados['access_token'] ?? null)) {
            unset($dados['access_token']);
        }

        $config->update([
            ...$dados,
            'atualizado_por_user_id' => $request->user()?->id,
        ]);

        if ($tokenAlterado) {
            $this->registrarVerificacaoToken($config->fresh(), $client);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $this->mensagemSalvarCampanha($config->fresh(), 'salva'),
        ]);

        return redirect()->route('trafego.index');
    }

    public function destroy(MetaConversaoConfig $config): RedirectResponse
    {
        if ($config->eventos()->exists()) {
            $config->update(['ativo' => false]);

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Campanha desativada (há histórico de eventos).',
            ]);

            return redirect()->route('trafego.index');
        }

        $nome = $config->nome_campanha;
        $config->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Campanha {$nome} removida.",
        ]);

        return redirect()->route('trafego.index');
    }

    public function testarConexao(
        TestarConexaoMetaRequest $request,
        MetaConversaoConfig $config,
        ConversionsApiClient $client,
    ): RedirectResponse {
        $tokenNovo = $request->filled('access_token')
            ? $request->string('access_token')->toString()
            : null;

        $resultado = $client->verificarConexao($config, $tokenNovo);

        // Só registra a verificação quando o token testado é o que está salvo.
        if ($tokenNovo === null) {
            $config->forceFill([
                'token_verificado_em' => now(),
                'token_valido' => $resultado->ok,
            ])->saveQuietly();
        }

        if ($resultado->ok) {
            $nome = $resultado->responseBody['name'] ?? null;
            $ultimoDisparo = $resultado->responseBody['last_fired_time'] ?? null;
            $viaEnvio = ($resultado->responseBody['verification'] ?? null) === 'events';

            $mensagem = $viaEnvio
                ? 'Token válido para envio de eventos na CAPI'
                : 'Pixel '.(is_string($nome) && $nome !== '' ? $nome : $config->pixel_id);

            if (is_string($ultimoDisparo) && $ultimoDisparo !== '') {
                $mensagem .= ' · último disparo '.$ultimoDisparo;
            }

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => $mensagem,
            ]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'error',
            'message' => $resultado->errorMessage
                ?? 'Não foi possível validar o Pixel na Meta (HTTP '.($resultado->httpStatus ?? 'conexão').').',
        ]);

        return back();
    }

    public function eventoTeste(
        EventoTesteMetaRequest $request,
        MetaConversaoConfig $config,
        CapiPayloadBuilder $payloadBuilder,
    ): RedirectResponse {
        $codigo = $request->filled('test_event_code')
            ? $request->string('test_event_code')->toString()
            : (string) $config->test_event_code;

        if ($codigo === '') {
            throw ValidationException::withMessages([
                'test_event_code' => 'Defina um código de teste (test_event_code) na campanha ou informe um aqui.',
            ]);
        }

        // Um código enviado no corpo passa a valer para a campanha (o cliente
        // HTTP lê o valor salvo).
        if ($request->filled('test_event_code') && (string) $config->test_event_code !== $codigo) {
            $config->forceFill(['test_event_code' => $codigo])->save();
        }

        $eventId = 'teste_'.Str::uuid()->toString();

        $userData = [
            'em' => [AdvancedMatching::hash('teste@asfadvogados.adv.br')],
            'fn' => [AdvancedMatching::hash('Teste')],
            'ln' => [AdvancedMatching::hash('CRM')],
            'country' => [AdvancedMatching::country()],
            'external_id' => [AdvancedMatching::externalId('trafego-teste-'.$config->id)],
        ];

        $payload = $payloadBuilder->build(
            MetaEventName::Lead,
            $eventId,
            now(),
            'system_generated',
            $userData,
            [
                'lead_event_source' => 'crm_asfadvogados_teste',
                'content_name' => $config->nome_campanha,
            ],
            $config->origem_url,
        );

        $evento = MetaConversaoEvento::query()->create([
            'meta_conversao_config_id' => $config->id,
            'event_name' => MetaEventName::Lead,
            'event_id' => $eventId,
            'event_time' => now(),
            'action_source' => 'system_generated',
            'status' => MetaConversaoEventoStatus::Pendente,
            'request_payload' => $payload,
            'is_teste' => true,
        ]);

        dispatch_sync(new EnviarEventoConversaoMeta($evento));
        $evento->refresh();

        if ($evento->status === MetaConversaoEventoStatus::Enviado) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Evento de teste recebido pela Meta ('.($evento->events_received ?? 1).')'
                    .($evento->fbtrace_id !== null ? ' · fbtrace '.$evento->fbtrace_id : ''),
            ]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'error',
            'message' => $evento->error_message
                ?? 'A Meta não confirmou o recebimento do evento de teste.',
        ]);

        return back();
    }

    private function registrarVerificacaoToken(
        MetaConversaoConfig $config,
        ConversionsApiClient $client,
    ): void {
        $resultado = $client->verificarConexao($config);

        $config->forceFill([
            'token_verificado_em' => now(),
            'token_valido' => $resultado->ok,
        ])->saveQuietly();
    }

    private function mensagemSalvarCampanha(MetaConversaoConfig $config, string $acao): string
    {
        $mensagem = $acao === 'cadastrada'
            ? "Campanha {$config->nome_campanha} cadastrada."
            : "Configuração da campanha {$config->nome_campanha} salva.";

        if ($config->token_valido === true) {
            return $mensagem.' Token validado para envio na CAPI.';
        }

        if ($config->token_valido === false) {
            return $mensagem.' Token não validado — use “Testar conexão” para ver o erro da Meta.';
        }

        return $mensagem;
    }

    /**
     * Projeção da campanha para o front — nunca inclui o `access_token`.
     *
     * @return array<string, mixed>
     */
    private function campanhaSegura(MetaConversaoConfig $config): array
    {
        return [
            'id' => $config->id,
            'nome_campanha' => $config->nome_campanha,
            'slug' => $config->slug,
            'pixel_id' => $config->pixel_id,
            'token_mascarado' => $config->mascararToken(),
            'token_definido' => $config->token_ultimos4 !== null,
            'token_verificado_em' => $config->token_verificado_em?->toIso8601String(),
            'token_valido' => $config->token_valido,
            'test_event_definido' => $config->getRawOriginal('test_event_code') !== null,
            'api_version' => $config->api_version,
            'action_source' => $config->action_source,
            'origem_url' => $config->origem_url,
            'finalidade_consentimento_slug' => $config->finalidade_consentimento_slug,
            'ativo' => $config->ativo,
            'ultimo_evento_em' => $config->ultimo_evento_em?->toIso8601String(),
            'ultimo_status' => $config->ultimo_status,
            'atualizado_por' => $config->atualizadoPor?->name,
        ];
    }
}

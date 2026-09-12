<?php

namespace App\Http\Controllers;

use App\Enums\MetaConversaoEventoStatus;
use App\Enums\MetaEventName;
use App\Http\Resources\MetaConversaoEventoResource;
use App\Jobs\EnviarEventoConversaoMeta;
use App\Models\MetaConversaoConfig;
use App\Models\MetaConversaoEvento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Log de eventos enviados à API de Conversões (`/trafego/eventos`). A Meta não
 * expõe releitura dos eventos → esta tabela local é a fonte de verdade.
 */
class TrafegoEventoController extends Controller
{
    private const PERIODOS = [7, 30, 90];

    public function index(Request $request): Response
    {
        return Inertia::render('crm/TrafegoEventos', [
            ...$this->listaProps($request),
            'detalhe' => null,
        ]);
    }

    public function show(Request $request, MetaConversaoEvento $evento): Response
    {
        $evento->load(['config:id,nome_campanha,slug', 'contato:id,nome']);

        return Inertia::render('crm/TrafegoEventos', [
            ...$this->listaProps($request),
            'detalhe' => [
                ...(new MetaConversaoEventoResource($evento))->resolve($request),
                'request_payload' => $evento->request_payload,
                'response_body' => $evento->response_body,
            ],
        ]);
    }

    public function reenviar(MetaConversaoEvento $evento): RedirectResponse
    {
        if ($evento->status !== MetaConversaoEventoStatus::Erro) {
            throw ValidationException::withMessages([
                'status' => 'Só eventos com status "erro" podem ser recolocados na fila.',
            ]);
        }

        // Mantém a mesma linha e o mesmo event_id — só reprograma o envio.
        $evento->forceFill(['status' => MetaConversaoEventoStatus::Pendente])->save();

        EnviarEventoConversaoMeta::dispatch($evento)->onQueue((string) config('meta.capi.queue'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Evento {$evento->event_id} recolocado na fila de envio.",
        ]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function listaProps(Request $request): array
    {
        $periodo = (int) $request->integer('periodo', 30);
        $periodo = in_array($periodo, self::PERIODOS, true) ? $periodo : 30;

        $campanha = $request->string('campanha')->toString();
        $status = $request->string('status')->toString();
        $eventName = $request->string('event_name')->toString();
        $busca = trim($request->string('busca')->toString());

        $filtrar = fn ($query) => $query
            ->where('created_at', '>=', now()->subDays($periodo))
            ->when($campanha !== '', fn ($q) => $q->whereRelation('config', 'slug', $campanha))
            ->when($eventName !== '', fn ($q) => $q->where('event_name', $eventName))
            ->when($busca !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('event_id', 'like', "%{$busca}%")
                ->orWhere('fbtrace_id', 'like', "%{$busca}%")));

        $eventos = $filtrar(
            MetaConversaoEvento::query()->with(['config:id,nome_campanha,slug', 'contato:id,nome'])
        )
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (MetaConversaoEvento $e) => (new MetaConversaoEventoResource($e))->resolve($request));

        $contadores = $filtrar(MetaConversaoEvento::query())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'eventos' => $eventos,
            'filtros' => [
                'campanha' => $campanha,
                'status' => $status,
                'event_name' => $eventName,
                'periodo' => $periodo,
                'busca' => $busca,
            ],
            'contadores' => collect(MetaConversaoEventoStatus::cases())
                ->mapWithKeys(fn (MetaConversaoEventoStatus $s) => [
                    $s->value => (int) ($contadores[$s->value] ?? 0),
                ]),
            'opcoes' => [
                'campanhas' => MetaConversaoConfig::query()
                    ->orderBy('nome_campanha')
                    ->get(['slug', 'nome_campanha'])
                    ->map(fn (MetaConversaoConfig $c) => ['slug' => $c->slug, 'nome' => $c->nome_campanha]),
                'status' => collect(MetaConversaoEventoStatus::cases())
                    ->map(fn (MetaConversaoEventoStatus $s) => ['value' => $s->value, 'label' => $s->label()]),
                'eventos' => collect(MetaEventName::cases())
                    ->map(fn (MetaEventName $e) => ['value' => $e->value, 'label' => $e->label()]),
                'periodos' => self::PERIODOS,
            ],
        ];
    }
}

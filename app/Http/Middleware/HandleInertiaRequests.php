<?php

namespace App\Http\Middleware;

use App\Enums\EtapaFunilResultado;
use App\Enums\StatusTarefaNegociacao;
use App\Enums\TipoTarefaNegociacao;
use App\Models\Contato;
use App\Models\Empresa;
use App\Models\MetaConversaoConfig;
use App\Models\Negociacao;
use App\Models\Proposta;
use App\Models\TarefaNegociacao;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'tenant' => fn (): ?array => app(CurrentTenant::class)->resolved()
                ? ['name' => app(CurrentTenant::class)->get()->name, 'slug' => app(CurrentTenant::class)->get()->slug]
                : null,
            // Relies on ResolveTenant having run first (appended early in the `web`
            // group in bootstrap/app.php, right after Laravel's own session/auth
            // middleware) so these queries are already filtered to the current
            // tenant by TenantScope — without that, TenantScope fails closed and every
            // count below would silently read as zero rather than leaking other tenants.
            'crmCounts' => function () use ($request): ?array {
                if ($request->user() === null) {
                    return null;
                }

                return [
                    'contatos' => (string) Contato::query()->count(),
                    'empresas' => (string) Empresa::query()->count(),
                    'negociacoes' => (string) Negociacao::query()->count(),
                    'propostas' => (string) Proposta::query()->count(),
                    'trafego' => (string) MetaConversaoConfig::query()->ativas()->count(),
                    // Follow-ups do usuário vencidos ou de hoje (badge do "Meu dia").
                    'meuDia' => (string) TarefaNegociacao::query()
                        ->where('responsavel_user_id', $request->user()->id)
                        ->where('tipo', TipoTarefaNegociacao::FollowUp->value)
                        ->whereIn('status', StatusTarefaNegociacao::abertos())
                        ->whereDate('data', '<=', now(config('crm.timezone'))->toDateString())
                        ->whereHas('negociacao.etapaFunil', fn (Builder $query) => $query->where('resultado', EtapaFunilResultado::Aberta->value))
                        ->count(),
                ];
            },
            'notificacoes' => function () use ($request): ?array {
                if ($request->user() === null) {
                    return null;
                }

                $naoLidas = $request->user()->unreadNotifications();

                return [
                    'naoLidas' => $naoLidas->count(),
                    'itens' => $naoLidas->limit(8)->get()->map(fn (DatabaseNotification $notificacao): array => [
                        'id' => $notificacao->id,
                        'criadaEm' => $notificacao->created_at?->toIso8601String(),
                        ...$notificacao->data,
                    ])->values()->all(),
                ];
            },
        ];
    }
}

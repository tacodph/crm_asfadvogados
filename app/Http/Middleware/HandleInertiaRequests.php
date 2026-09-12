<?php

namespace App\Http\Middleware;

use App\Models\Contato;
use App\Models\Empresa;
use App\Models\MetaConversaoConfig;
use App\Models\Negociacao;
use App\Models\Proposta;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\Request;
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
                ];
            },
        ];
    }
}

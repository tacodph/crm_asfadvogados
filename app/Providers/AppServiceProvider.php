<?php

namespace App\Providers;

use App\Models\Role;
use App\Models\User;
use App\Support\Meta\ElegibilidadeEventoMeta;
use App\Support\Tenancy\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CurrentTenant::class);
        $this->app->singleton(ElegibilidadeEventoMeta::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->configureGates();
    }

    /**
     * Agenda da equipe ("Meu dia" e Atividades no escopo "equipe"): só
     * dono, admin e gerente veem as tarefas dos outros usuários.
     */
    protected function configureGates(): void
    {
        Gate::define('ver-agenda-equipe', fn (User $user): bool => in_array(
            $user->role,
            [Role::OWNER, Role::ADMIN, Role::GERENTE],
            true,
        ));
    }

    /**
     * Limite do grupo `api` — hoje só o endpoint público de captação de leads
     * (POST /api/trafego/leads). Por IP, suficiente para um formulário de site.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->ip() ?? 'sem-ip'));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}

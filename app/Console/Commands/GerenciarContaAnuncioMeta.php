<?php

namespace App\Console\Commands;

use App\Models\MetaAdsConta;
use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[Signature('meta:ads-conta {--tenant= : Slug do tenant} {--conta= : ad_account_id (só dígitos)} {--nome= : Rótulo interno}')]
#[Description('Cadastra ou atualiza uma conta de anúncio do Meta Ads (provisionamento sem UI)')]
class GerenciarContaAnuncioMeta extends Command
{
    public function handle(): int
    {
        $tenant = $this->resolverTenant();

        if ($tenant === null) {
            $this->components->error('Nenhum tenant encontrado para operar.');

            return self::FAILURE;
        }

        return app(CurrentTenant::class)->runAs($tenant, fn (): int => $this->gerenciar());
    }

    private function gerenciar(): int
    {
        $adAccountId = $this->normalizarId(
            $this->option('conta') ?: text(
                'ad_account_id (só dígitos, sem "act_")',
                required: true,
                validate: fn (string $v): ?string => preg_match('/^\d{6,20}$/', $this->normalizarId($v)) === 1
                    ? null
                    : 'Informe apenas dígitos (6 a 20).',
            )
        );

        $base = MetaAdsConta::query()->where('ad_account_id', $adAccountId);
        $existe = (clone $base)->exists();
        $nomeAtual = (clone $base)->value('nome');

        $nome = $this->option('nome') ?: text(
            'Rótulo interno da conta',
            default: is_string($nomeAtual) ? $nomeAtual : '',
            required: true,
        );

        $token = password(
            $existe
                ? 'Access token (ads_read) — Enter em branco mantém o atual'
                : 'Access token (ads_read)',
        );

        $dados = ['nome' => $nome];

        if ($token !== '') {
            $dados['access_token'] = $token;
        } elseif (! $existe) {
            $this->components->error('O access token é obrigatório ao cadastrar uma nova conta.');

            return self::FAILURE;
        }

        $conta = MetaAdsConta::query()->updateOrCreate(['ad_account_id' => $adAccountId], $dados);

        $this->components->info(sprintf(
            '%s: %s [act_%s]',
            $existe ? 'Conta atualizada' : 'Conta cadastrada',
            $conta->nome,
            $conta->ad_account_id,
        ));
        $this->components->info('Rode `php artisan meta:ads-sincronizar` para validar o token e trazer os dados.');

        return self::SUCCESS;
    }

    private function normalizarId(string $valor): string
    {
        return preg_replace('/\D/', '', $valor) ?? '';
    }

    private function resolverTenant(): ?Tenant
    {
        $slug = $this->option('tenant');

        if (is_string($slug) && $slug !== '') {
            return Tenant::query()->where('slug', $slug)->first();
        }

        $tenants = Tenant::query()->orderBy('name')->get();

        if ($tenants->count() === 1) {
            return $tenants->first();
        }

        if ($tenants->isEmpty()) {
            return null;
        }

        $escolhido = select('Tenant', $tenants->pluck('name', 'slug')->all());

        return $tenants->firstWhere('slug', $escolhido);
    }
}

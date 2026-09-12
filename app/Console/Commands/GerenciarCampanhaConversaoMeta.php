<?php

namespace App\Console\Commands;

use App\Models\MetaConversaoConfig;
use App\Models\Tenant;
use App\Support\Meta\ConversionsApiClient;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[Signature('meta:campanha {--tenant= : Slug do tenant} {--slug= : Slug da campanha} {--testar : Valida o token na Graph API após salvar}')]
#[Description('Cadastra ou atualiza uma campanha da API de Conversões da Meta (provisionamento sem UI)')]
class GerenciarCampanhaConversaoMeta extends Command
{
    /**
     * Execute the console command.
     */
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
        $slug = Str::slug(
            $this->option('slug') ?: text('Slug da campanha', placeholder: 'bancario', required: true)
        );

        $base = MetaConversaoConfig::query()->where('slug', $slug);

        $existe = (clone $base)->exists();
        $nomeAtual = (clone $base)->value('nome_campanha');
        $pixelAtual = (clone $base)->value('pixel_id');
        $finalidadeAtual = (clone $base)->value('finalidade_consentimento_slug');

        $nome = text(
            'Nome da campanha',
            default: is_string($nomeAtual) ? $nomeAtual : '',
            required: true,
        );

        $pixelId = text(
            'Pixel / Dataset ID',
            default: is_string($pixelAtual) ? $pixelAtual : '',
            required: true,
            validate: fn (string $value): ?string => preg_match('/^\d{6,20}$/', $value) === 1
                ? null
                : 'Informe apenas dígitos (6 a 20).',
        );

        $token = password(
            $existe
                ? 'Access token (Enter em branco mantém o atual)'
                : 'Access token',
        );

        $finalidade = text(
            'Slug da finalidade de consentimento',
            default: is_string($finalidadeAtual) ? $finalidadeAtual : 'marketing',
        );

        $dados = [
            'nome_campanha' => $nome,
            'pixel_id' => $pixelId,
            'finalidade_consentimento_slug' => $finalidade !== '' ? $finalidade : null,
        ];

        if ($token !== '') {
            $dados['access_token'] = $token;
        } elseif (! $existe) {
            $this->components->error('O access token é obrigatório ao cadastrar uma nova campanha.');

            return self::FAILURE;
        }

        $config = MetaConversaoConfig::query()->updateOrCreate(['slug' => $slug], $dados);

        $this->components->info(sprintf(
            '%s: %s [%s] · pixel %s',
            $existe ? 'Campanha atualizada' : 'Campanha cadastrada',
            $config->nome_campanha,
            $config->slug,
            $config->pixel_id,
        ));

        if ($this->option('testar')) {
            $this->testarConexao($config);
        }

        return self::SUCCESS;
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

        $escolhido = select(
            'Tenant',
            $tenants->pluck('name', 'slug')->all(),
        );

        return $tenants->firstWhere('slug', $escolhido);
    }

    private function testarConexao(MetaConversaoConfig $config): void
    {
        $resultado = app(ConversionsApiClient::class)->verificarConexao($config);

        $config->forceFill([
            'token_verificado_em' => now(),
            'token_valido' => $resultado->ok,
        ])->saveQuietly();

        if ($resultado->ok) {
            $nome = $resultado->responseBody['name'] ?? $config->pixel_id;
            $viaEnvio = ($resultado->responseBody['verification'] ?? null) === 'events';
            $this->components->info(
                $viaEnvio
                    ? 'Token válido para envio de eventos na CAPI'
                    : 'Token válido — Pixel: '.(is_string($nome) ? $nome : $config->pixel_id),
            );

            return;
        }

        $this->components->warn(
            'Falha ao validar o token: '
            .($resultado->errorMessage ?? ('HTTP '.($resultado->httpStatus ?? 'conexão')))
        );
    }
}

<?php

namespace App\Http\Requests\Concerns;

use App\Support\Tenancy\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Regras compartilhadas pelo cadastro/edição de campanhas da API de Conversões
 * (tela /trafego). O `access_token` fica a cargo de cada FormRequest, já que
 * varia entre obrigatório (criar) e opcional (editar/testar).
 */
trait ValidatesMetaCampanha
{
    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    protected function regrasCampanha(?int $ignorarConfigId = null): array
    {
        $tenantId = app(CurrentTenant::class)->id();

        return [
            'nome_campanha' => ['required', 'string', 'max:120'],
            'slug' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('meta_conversao_configs', 'slug')
                    ->where('tenant_id', $tenantId)
                    ->ignore($ignorarConfigId),
            ],
            'pixel_id' => ['required', 'string', 'regex:/^\d{6,20}$/'],
            'api_version' => ['required', 'string', 'regex:/^v\d+\.\d+$/'],
            'action_source' => [
                'required',
                'string',
                Rule::in(['website', 'system_generated', 'app', 'chat', 'email', 'phone_call', 'other']),
            ],
            'origem_url' => ['nullable', 'url', 'max:2048'],
            'finalidade_consentimento_slug' => [
                'nullable',
                'string',
                Rule::exists('finalidades_consentimento', 'slug')->where('tenant_id', $tenantId),
            ],
            'test_event_code' => ['nullable', 'string', 'max:64'],
            'ativo' => ['boolean'],
        ];
    }

    /**
     * Normaliza os campos antes da validação: slug derivado do nome quando
     * vazio e defaults dos selects (para não recusar submissões parciais).
     */
    protected function prepararCampanha(): void
    {
        $nome = $this->string('nome_campanha')->trim()->toString();

        $slug = $this->filled('slug')
            ? Str::slug($this->string('slug')->toString())
            : Str::slug($nome);

        $this->merge([
            'nome_campanha' => $nome,
            'slug' => $slug,
            'access_token' => $this->filled('access_token')
                ? trim($this->string('access_token')->toString())
                : $this->input('access_token'),
            'test_event_code' => $this->filled('test_event_code')
                ? trim($this->string('test_event_code')->toString())
                : $this->input('test_event_code'),
            'api_version' => $this->filled('api_version')
                ? $this->string('api_version')->toString()
                : (string) config('meta.capi.api_version', 'v21.0'),
            'action_source' => $this->filled('action_source')
                ? $this->string('action_source')->toString()
                : (string) config('meta.capi.default_action_source', 'system_generated'),
            'ativo' => $this->boolean('ativo', true),
        ]);
    }
}

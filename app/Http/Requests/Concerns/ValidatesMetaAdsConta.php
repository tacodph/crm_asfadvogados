<?php

namespace App\Http\Requests\Concerns;

use App\Support\Tenancy\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Regras compartilhadas pelo cadastro/edição de contas de anúncio do Meta Ads
 * (aba /trafego → Investimento). O `access_token` fica a cargo de cada
 * FormRequest (obrigatório ao criar, opcional ao editar).
 */
trait ValidatesMetaAdsConta
{
    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    protected function regrasConta(?int $ignorarContaId = null): array
    {
        $tenantId = app(CurrentTenant::class)->id();

        return [
            'nome' => ['required', 'string', 'max:120'],
            'ad_account_id' => [
                'required',
                'string',
                'regex:/^\d{6,20}$/',
                Rule::unique('meta_ads_contas', 'ad_account_id')
                    ->where('tenant_id', $tenantId)
                    ->ignore($ignorarContaId),
            ],
            'business_id' => ['nullable', 'string', 'regex:/^\d{6,20}$/'],
        ];
    }

    protected function prepararConta(): void
    {
        $this->merge([
            'nome' => $this->string('nome')->trim()->toString(),
            'ad_account_id' => preg_replace('/\D/', '', $this->string('ad_account_id')->toString()) ?? '',
            'access_token' => $this->filled('access_token')
                ? trim($this->string('access_token')->toString())
                : $this->input('access_token'),
        ]);
    }
}

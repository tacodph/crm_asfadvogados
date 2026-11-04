<?php

namespace App\Http\Requests;

use App\Enums\EtapaFunilResultado;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BuscarNegociacoesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = app(CurrentTenant::class)->id();

        return [
            'q' => ['nullable', 'string', 'max:100'],
            'responsavel_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'etapa_id' => ['nullable', 'integer', Rule::exists('etapas_funil', 'id')->where('tenant_id', $tenantId)],
            'resultado' => ['nullable', Rule::enum(EtapaFunilResultado::class)],
            'status_atendimento_id' => ['nullable', 'integer'],
            'status_qualificacao_id' => ['nullable', 'integer'],
        ];
    }
}

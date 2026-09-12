<?php

namespace App\Http\Requests;

use App\Models\IbgeMunicipio;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmpresaRequest extends FormRequest
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
        $empresa = $this->route('empresa');

        return [
            'nome' => ['required', 'string', 'max:255'],
            'cnpj' => [
                'required',
                'string',
                'max:20',
                Rule::unique('empresas', 'cnpj')
                    ->where('tenant_id', $tenantId)
                    ->ignore($empresa->id),
            ],
            'setor_id' => [
                'required',
                'integer',
                Rule::exists('setores', 'id')->where('tenant_id', $tenantId),
            ],
            'porte' => ['required', 'string', 'max:255'],
            'municipio_id' => [
                'required',
                'integer',
                Rule::exists(IbgeMunicipio::class, 'id'),
            ],
            'status_conflito_id' => [
                'required',
                'integer',
                Rule::exists('status_conflitos', 'id')->where('tenant_id', $tenantId),
            ],
            'status_comercial_id' => [
                'required',
                'integer',
                Rule::exists('status_comerciais', 'id')->where('tenant_id', $tenantId),
            ],
            'conflito_texto' => ['nullable', 'string', 'max:2000'],
            'responsavel_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
        ];
    }
}

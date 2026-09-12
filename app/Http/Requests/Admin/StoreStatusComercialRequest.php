<?php

namespace App\Http\Requests\Admin;

use App\Support\Tenancy\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreStatusComercialRequest extends FormRequest
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
            'nome' => [
                'required',
                'string',
                'max:255',
                Rule::unique('status_comerciais', 'nome')->where('tenant_id', $tenantId),
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('status_comerciais', 'slug')->where('tenant_id', $tenantId),
            ],
            'descricao' => ['nullable', 'string', 'max:500'],
            'cor_fundo' => ['required', 'string', 'max:9'],
            'cor_texto' => ['required', 'string', 'max:9'],
            'ordem' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $nome = $this->string('nome')->toString();
        $slug = $this->filled('slug')
            ? Str::slug($this->string('slug')->toString())
            : Str::slug($nome);

        $this->merge([
            'nome' => $nome,
            'slug' => $slug,
            'descricao' => $this->filled('descricao') ? $this->string('descricao')->toString() : null,
            'cor_fundo' => $this->filled('cor_fundo') ? $this->string('cor_fundo')->toString() : '#F4F2EC',
            'cor_texto' => $this->filled('cor_texto') ? $this->string('cor_texto')->toString() : '#77808E',
            'ordem' => $this->filled('ordem') ? $this->integer('ordem') : 0,
        ]);
    }
}

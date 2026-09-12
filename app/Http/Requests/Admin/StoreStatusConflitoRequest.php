<?php

namespace App\Http\Requests\Admin;

use App\Support\Tenancy\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreStatusConflitoRequest extends FormRequest
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
                Rule::unique('status_conflitos', 'nome')->where('tenant_id', $tenantId),
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('status_conflitos', 'slug')->where('tenant_id', $tenantId),
            ],
            'cor_fundo' => ['required', 'string', 'max:9'],
            'cor_texto' => ['required', 'string', 'max:9'],
            'cor_fundo_detalhe' => ['required', 'string', 'max:9'],
            'cor_borda_detalhe' => ['required', 'string', 'max:9'],
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
            'cor_fundo' => $this->filled('cor_fundo') ? $this->string('cor_fundo')->toString() : '#E7F0EE',
            'cor_texto' => $this->filled('cor_texto') ? $this->string('cor_texto')->toString() : '#14574F',
            'cor_fundo_detalhe' => $this->filled('cor_fundo_detalhe') ? $this->string('cor_fundo_detalhe')->toString() : '#F1F6F4',
            'cor_borda_detalhe' => $this->filled('cor_borda_detalhe') ? $this->string('cor_borda_detalhe')->toString() : '#CCE0DA',
            'ordem' => $this->filled('ordem') ? $this->integer('ordem') : 0,
        ]);
    }
}

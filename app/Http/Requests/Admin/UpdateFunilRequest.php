<?php

namespace App\Http\Requests\Admin;

use App\Models\Funil;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateFunilRequest extends FormRequest
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
        /** @var Funil $funil */
        $funil = $this->route('funil');
        $tenantId = app(CurrentTenant::class)->id();

        return [
            'nome' => [
                'required',
                'string',
                'max:255',
                Rule::unique('funis', 'nome')
                    ->where('tenant_id', $tenantId)
                    ->ignore($funil->id),
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('funis', 'slug')
                    ->where('tenant_id', $tenantId)
                    ->ignore($funil->id),
            ],
            'distribuicao' => ['required', 'string', Rule::in(Funil::REGRAS_DISTRIBUICAO)],
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
            'distribuicao' => $this->string('distribuicao')->toString(),
            'ordem' => $this->filled('ordem') ? $this->integer('ordem') : 0,
        ]);
    }
}

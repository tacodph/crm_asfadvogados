<?php

namespace App\Http\Requests\Admin;

use App\Models\CanalContato;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateCanalContatoRequest extends FormRequest
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
        /** @var CanalContato $canal */
        $canal = $this->route('canal_contato');
        $tenantId = app(CurrentTenant::class)->id();

        return [
            'nome' => [
                'required',
                'string',
                'max:255',
                Rule::unique('canais_contato', 'nome')
                    ->where('tenant_id', $tenantId)
                    ->ignore($canal->id),
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('canais_contato', 'slug')
                    ->where('tenant_id', $tenantId)
                    ->ignore($canal->id),
            ],
            'cor' => ['required', 'string', 'max:9'],
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
            'cor' => $this->string('cor')->toString(),
            'ordem' => $this->filled('ordem') ? $this->integer('ordem') : 0,
        ]);
    }
}

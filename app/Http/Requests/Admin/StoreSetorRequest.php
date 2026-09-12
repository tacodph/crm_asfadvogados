<?php

namespace App\Http\Requests\Admin;

use App\Support\Tenancy\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreSetorRequest extends FormRequest
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
                Rule::unique('setores', 'nome')->where('tenant_id', $tenantId),
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('setores', 'slug')->where('tenant_id', $tenantId),
            ],
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
            'ordem' => $this->filled('ordem') ? $this->integer('ordem') : 0,
        ]);
    }
}

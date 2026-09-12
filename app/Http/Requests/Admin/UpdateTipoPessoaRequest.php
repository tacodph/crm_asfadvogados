<?php

namespace App\Http\Requests\Admin;

use App\Models\TipoPessoa;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateTipoPessoaRequest extends FormRequest
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
        /** @var TipoPessoa $tipo */
        $tipo = $this->route('tipo_pessoa');

        return [
            'nome' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tipos_pessoa', 'nome')->ignore($tipo->id),
            ],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tipos_pessoa', 'slug')->ignore($tipo->id),
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

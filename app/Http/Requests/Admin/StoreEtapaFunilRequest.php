<?php

namespace App\Http\Requests\Admin;

use App\Enums\MetaEventName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEtapaFunilRequest extends FormRequest
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
        return [
            'nome' => ['required', 'string', 'max:255'],
            'sla' => ['required', 'string', 'max:50'],
            'campos' => ['nullable', 'array'],
            'campos.*' => ['string', 'max:100'],
            'meta_evento' => ['nullable', Rule::enum(MetaEventName::class)],
            'exige_motivo' => ['required', 'boolean'],
            'ordem' => ['required', 'integer', 'min:0', 'max:65535'],
            'cor_fundo' => ['required', 'string', 'max:9'],
            'cor_texto' => ['required', 'string', 'max:9'],
            'cor_suave' => ['required', 'string', 'max:64'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $campos = $this->input('campos');

        if (is_string($campos)) {
            $campos = collect(explode(',', $campos))
                ->map(fn (string $item): string => trim($item))
                ->filter()
                ->values()
                ->all();
        }

        $this->merge([
            'nome' => $this->string('nome')->toString(),
            'sla' => $this->filled('sla') ? $this->string('sla')->toString() : '—',
            'campos' => is_array($campos) ? $campos : [],
            'meta_evento' => $this->filled('meta_evento') ? $this->string('meta_evento')->toString() : null,
            'exige_motivo' => $this->boolean('exige_motivo'),
            'ordem' => $this->filled('ordem') ? $this->integer('ordem') : 0,
            'cor_fundo' => $this->filled('cor_fundo') ? $this->string('cor_fundo')->toString() : '#14574F',
            'cor_texto' => $this->filled('cor_texto') ? $this->string('cor_texto')->toString() : '#FBF9F4',
            'cor_suave' => $this->filled('cor_suave')
                ? $this->string('cor_suave')->toString()
                : 'rgba(251,249,244,0.9)',
        ]);
    }
}

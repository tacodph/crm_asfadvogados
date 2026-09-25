<?php

namespace App\Http\Requests\Admin;

use App\Enums\MetaEventName;
use App\Models\Funil;
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
            'ordem' => ['required', 'integer', 'min:0', 'max:'.$this->maxOrdemPermitida()],
            'cor_fundo' => ['required', 'string', 'max:9'],
            'cor_texto' => ['required', 'string', 'max:9'],
            'cor_suave' => ['required', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ordem.max' => 'A ordem não pode ser maior que o número de etapas (:max).',
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
            'cor_fundo' => $this->hexColorOrDefault($this->input('cor_fundo'), '#14574F'),
            'cor_texto' => $this->hexColorOrDefault($this->input('cor_texto'), '#FBF9F4'),
            'cor_suave' => $this->filled('cor_suave')
                ? $this->string('cor_suave')->toString()
                : 'rgba(251,249,244,0.9)',
        ]);
    }

    private function maxOrdemPermitida(): int
    {
        $funil = $this->route('funil');

        if (! $funil instanceof Funil) {
            return 0;
        }

        return $funil->etapas()->count();
    }

    private function hexColorOrDefault(mixed $value, string $default): string
    {
        if (! is_string($value)) {
            return $default;
        }

        $color = trim($value);

        if (preg_match('/^#[0-9A-Fa-f]{6}([0-9A-Fa-f]{2})?$/', $color) === 1) {
            return strtoupper($color);
        }

        return $default;
    }
}

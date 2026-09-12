<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesMetaCampanha;
use App\Models\MetaConversaoConfig;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMetaConversaoConfigRequest extends FormRequest
{
    use ValidatesMetaCampanha;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        /** @var MetaConversaoConfig $config */
        $config = $this->route('config');

        return [
            ...$this->regrasCampanha($config->id),
            // Em branco = manter o token atual (o controller descarta a chave).
            'access_token' => ['nullable', 'string', 'min:20'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->prepararCampanha();
    }
}

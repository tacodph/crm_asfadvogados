<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class EventoTesteMetaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        // Opcional aqui; se a campanha também não tiver código salvo o
        // controller devolve 422 (`test_event_code`).
        return [
            'test_event_code' => ['nullable', 'string', 'max:64'],
        ];
    }
}

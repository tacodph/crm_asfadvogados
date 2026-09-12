<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TestarConexaoMetaRequest extends FormRequest
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
        // Token opcional: permite validar uma chave nova antes de salvá-la.
        return [
            'access_token' => ['nullable', 'string', 'min:20'],
        ];
    }
}

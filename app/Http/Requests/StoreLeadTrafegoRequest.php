<?php

namespace App\Http\Requests;

use App\Support\Tenancy\CurrentTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Payload do endpoint público POST /api/trafego/leads. O tenant já foi
 * resolvido pelo middleware do token — daí `authorize()` liberar.
 */
class StoreLeadTrafegoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CurrentTenant::class)->resolved();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tenantId = app(CurrentTenant::class)->id();

        return [
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'cpf' => ['nullable', 'string', 'max:14'],
            'cidade' => ['nullable', 'string', 'max:120'],
            'uf' => ['nullable', 'string', 'size:2'],
            'cep' => ['nullable', 'string', 'max:9'],

            // Base legal: a finalidade do opt-in do formulário + o aceite.
            'finalidade' => [
                'required',
                'string',
                Rule::exists('finalidades_consentimento', 'slug')->where('tenant_id', $tenantId),
            ],
            'consentimento' => ['accepted'],

            'assunto' => ['nullable', 'string', 'max:255'],
            'valor' => ['nullable', 'numeric', 'min:0'],
            'funil_slug' => ['nullable', 'string', 'max:255'],
            'canal' => ['nullable', 'string', 'max:255'],

            // Pixel do navegador (para deduplicação navegador ↔ CAPI).
            'meta_event_id' => ['nullable', 'string', 'max:255'],
            'meta_fbp' => ['nullable', 'string', 'max:255'],
            'meta_fbc' => ['nullable', 'string', 'max:255'],
            'meta_event_source_url' => ['nullable', 'string', 'max:2048'],

            // Preenchidos pelo backend da landing quando o POST é servidor-a-servidor.
            'client_ip_address' => ['nullable', 'ip'],
            'client_user_agent' => ['nullable', 'string', 'max:1024'],

            'utm_source' => ['nullable', 'string', 'max:255'],
            'utm_medium' => ['nullable', 'string', 'max:255'],
            'utm_campaign' => ['nullable', 'string', 'max:255'],
            'utm_content' => ['nullable', 'string', 'max:255'],
            'utm_term' => ['nullable', 'string', 'max:255'],
            'fbclid' => ['nullable', 'string', 'max:512'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if (! $this->filled('email') && ! $this->filled('telefone')) {
                    $validator->errors()->add('email', 'Informe e-mail ou telefone.');
                }
            },
        ];
    }
}

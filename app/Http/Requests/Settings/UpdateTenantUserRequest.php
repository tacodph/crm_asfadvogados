<?php

namespace App\Http\Requests\Settings;

use App\Concerns\PasswordValidationRules;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateTenantUserRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        /** @var User|null $actor */
        $actor = $this->user();
        /** @var User|null $target */
        $target = $this->route('user');

        return $actor !== null
            && $target instanceof User
            && $actor->canManageTenantUsers()
            && $actor->tenant_id === $target->tenant_id;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class, 'email')->ignore($target->id),
            ],
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
            'role' => ['required', 'string', Rule::in($this->allowedRoleValues())],
            'especialidades' => ['nullable', 'array'],
            'especialidades.*' => ['string', 'max:100'],
            'ausente_ate' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'especialidades' => $this->parseEspecialidades(),
            'ausente_ate' => $this->filled('ausente_ate') ? $this->string('ausente_ate')->toString() : null,
        ]);
    }

    /**
     * @return list<string>
     */
    private function allowedRoleValues(): array
    {
        /** @var User $actor */
        $actor = $this->user();
        /** @var User $target */
        $target = $this->route('user');

        Role::ensureDefaults();

        if ($actor->isOwner()) {
            return Role::query()->orderBy('ordem')->pluck('slug')->all();
        }

        if ($target->isOwner()) {
            return [Role::OWNER];
        }

        return Role::query()
            ->where('slug', '!=', Role::OWNER)
            ->orderBy('ordem')
            ->pluck('slug')
            ->all();
    }

    /**
     * @return list<string>
     */
    private function parseEspecialidades(): array
    {
        $raw = $this->input('especialidades');

        if (is_array($raw)) {
            return array_values(array_filter(array_map(
                fn (mixed $item): string => trim((string) $item),
                $raw,
            )));
        }

        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            'trim',
            explode(',', $raw),
        )));
    }
}

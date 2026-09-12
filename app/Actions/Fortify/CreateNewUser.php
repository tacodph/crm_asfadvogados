<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Invites a colleague onto the currently authenticated user's tenant. Public
 * self-registration (Fortify's guest-only /register) is disabled — see
 * config/fortify.php — because there's no tenant to assign a guest to under
 * normal (non-subdomain) login. This action is here for a future "invite a
 * colleague" admin feature to reuse.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return User::create([
            'tenant_id' => Auth::user()?->tenant_id,
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'role' => Role::MEMBER,
        ]);
    }
}

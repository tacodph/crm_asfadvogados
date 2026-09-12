<?php

namespace App\Http\Controllers\Onboarding;

use App\Actions\Tenancy\CreateTenantWithOwner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\CreateTenantRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class CreateTenantController extends Controller
{
    /**
     * Display the tenant signup form.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('onboarding/CreateTenant', [
            'plan' => $request->query('plan', 'escritorio'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Create the tenant, its owner user, and default catalogs, then log the
     * owner in and send them to the dashboard.
     */
    public function store(CreateTenantRequest $request, CreateTenantWithOwner $createTenantWithOwner): RedirectResponse
    {
        $provisioned = $createTenantWithOwner([
            'name' => (string) $request->validated('name'),
            'plan' => (string) $request->validated('plan'),
            'owner_name' => (string) $request->validated('owner_name'),
            'owner_email' => (string) $request->validated('owner_email'),
            'owner_password' => (string) $request->validated('owner_password'),
        ]);

        Auth::login($provisioned->owner);

        return redirect()->route('dashboard');
    }
}

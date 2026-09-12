<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

/**
 * Fortify's default `redirect('/')` is a relative path — on this app it
 * would resolve rooted at whatever tenant subdomain the user just logged
 * out from, where no route exists at "/" (only `home`, on the central
 * domain, has one). Send them there explicitly instead.
 */
class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        return redirect()->to(route('home'));
    }
}

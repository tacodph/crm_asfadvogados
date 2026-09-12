<?php

use App\Http\Controllers\CapturarLeadTrafegoController;
use App\Http\Middleware\ResolveTenantPorTokenLead;
use Illuminate\Support\Facades\Route;

/*
| Endpoint público de captação de leads pagos (site / landing / link-tree).
| Autenticado pelo segredo por tenant (`php artisan meta:trafego-token`).
| Ver documents/api_meta/captacao_leads_site.md.
*/
Route::post('trafego/leads', CapturarLeadTrafegoController::class)
    ->middleware(ResolveTenantPorTokenLead::class)
    ->name('api.trafego.leads');

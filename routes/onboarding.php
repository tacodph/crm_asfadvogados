<?php

use App\Http\Controllers\Onboarding\CreateTenantController;
use Illuminate\Support\Facades\Route;

Route::get('criar-conta', [CreateTenantController::class, 'create'])->name('tenants.create');
Route::post('criar-conta', [CreateTenantController::class, 'store'])->name('tenants.store');

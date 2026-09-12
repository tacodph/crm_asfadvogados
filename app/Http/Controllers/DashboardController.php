<?php

namespace App\Http\Controllers;

use App\Actions\Crm\BuildDashboardMetrics;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the CRM dashboard with live metrics.
     */
    public function index(Request $request, BuildDashboardMetrics $metrics): Response
    {
        $periodo = (string) $request->query('periodo', '30 dias');

        return Inertia::render('Dashboard', $metrics($periodo));
    }
}

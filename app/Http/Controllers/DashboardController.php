<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $dashboard): Response
    {
        return Inertia::render('Dashboard/Index', [
            'summary' => $dashboard->summary($request->user()),
        ]);
    }
}

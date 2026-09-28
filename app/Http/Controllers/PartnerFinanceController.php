<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Http\Resources\PartnerResource;
use App\Models\Partner;
use App\Services\Finance\PartnerStatementService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartnerFinanceController extends Controller
{
    public function dashboard(Request $request, Partner $partner, PartnerStatementService $statements): Response
    {
        $this->authorize('view', $partner);
        abort_unless($request->user()?->can(PermissionName::PartnerStatementView->value), 403);

        return Inertia::render('Partners/Dashboard', [
            'partner' => (new PartnerResource($partner))->resolve(),
            'position' => $statements->position($partner),
        ]);
    }

    public function statement(Request $request, Partner $partner, PartnerStatementService $statements): Response
    {
        $this->authorize('view', $partner);
        abort_unless($request->user()?->can(PermissionName::PartnerStatementView->value), 403);

        return Inertia::render('Partners/Statement', [
            'partner' => (new PartnerResource($partner))->resolve(),
            'lines' => $statements->statement($partner),
            'current_capital' => $statements->position($partner)['current_capital'],
        ]);
    }
}

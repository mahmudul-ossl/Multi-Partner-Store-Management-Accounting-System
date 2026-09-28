<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Services\AuditLogDirectory;
use App\Support\PaginatorPayload;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request, AuditLogDirectory $directory): Response
    {
        $this->authorize('viewAny', AuditLog::class);

        $filters = $request->only(['search', 'action']);

        return Inertia::render('AuditLogs/Index', [
            'logs' => PaginatorPayload::make($directory->paginate($filters), AuditLogResource::class),
            'filters' => [
                'search' => (string) ($filters['search'] ?? ''),
                'action' => (string) ($filters['action'] ?? ''),
            ],
            'actions' => AuditAction::options(),
        ]);
    }
}

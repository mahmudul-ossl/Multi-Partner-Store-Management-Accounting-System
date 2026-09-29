<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Models\ChartOfAccount;
use App\Models\Partner;
use App\Services\Reports\MonthlyReport;
use App\Services\Reports\ReportCatalog;
use App\Services\Reports\ReportTable;
use App\Support\SimplePdf;
use App\Support\Spreadsheet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user() !== null && ReportCatalog::visible($request->user()) !== [], 403);

        return Inertia::render('Reports/Index', [
            'reports' => ReportCatalog::visible($request->user()),
        ]);
    }

    public function show(Request $request, string $report, ReportTable $tables): Response
    {
        $definition = $this->definition($request, $report);
        $filters = $this->filters($request);
        $built = $tables->build($request->user(), $definition['key'], $filters);
        $page = max(1, (int) $request->integer('page', 1));
        $perPage = 15;
        $slice = array_slice($built['rows'], ($page - 1) * $perPage, $perPage);
        $total = count($built['rows']);

        return Inertia::render('Reports/Show', [
            'report' => ['key' => $definition['key'], 'label' => $definition['label']],
            'columns' => $built['columns'],
            'rows' => [
                'data' => $slice,
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'from' => $total === 0 ? null : (($page - 1) * $perPage) + 1,
                'to' => $total === 0 ? null : min($total, $page * $perPage),
                'total' => $total,
                'per_page' => $perPage,
            ],
            'totals' => $built['totals'],
            'filters' => $filters,
            'partners' => Partner::query()->orderBy('name')->get(['id', 'name', 'partner_code']),
            'accounts' => ChartOfAccount::query()->orderBy('code')->get(['id', 'code', 'name'])->map(fn (ChartOfAccount $account): array => [
                'id' => $account->id,
                'label' => $account->code.' · '.$account->name,
            ])->all(),
        ]);
    }

    public function excel(Request $request, string $report, ReportTable $tables): StreamedResponse
    {
        $definition = $this->definition($request, $report);
        $built = $tables->build($request->user(), $definition['key'], $this->filters($request));
        $headers = array_column($built['columns'], 'label');
        $keys = array_column($built['columns'], 'key');
        $rows = [];

        foreach ($built['rows'] as $row) {
            $line = [];
            foreach ($keys as $key) {
                $line[] = (string) ($row[$key] ?? '');
            }
            $rows[] = $line;
        }

        foreach ($built['totals'] as $total) {
            $rows[] = [$total['label'], $total['amount']];
        }

        return Spreadsheet::download($definition['key'].'.xlsx', $headers, $rows);
    }

    public function pdf(Request $request, string $report, ReportTable $tables): \Symfony\Component\HttpFoundation\Response
    {
        $definition = $this->definition($request, $report);
        $built = $tables->build($request->user(), $definition['key'], $this->filters($request));
        $lines = [$definition['label']];
        $keys = array_column($built['columns'], 'key');

        foreach ($built['rows'] as $row) {
            $parts = [];
            foreach ($keys as $key) {
                $parts[] = (string) ($row[$key] ?? '');
            }
            $lines[] = implode(' | ', $parts);
        }

        foreach ($built['totals'] as $total) {
            $lines[] = $total['label'].' '.$total['formatted'].' ('.$total['amount'].')';
        }

        return SimplePdf::download($definition['key'].'.pdf', $lines);
    }

    public function monthlyApi(Request $request, MonthlyReport $monthly): JsonResponse
    {
        abort_unless($request->user()?->can(PermissionName::ReportView->value), 403);
        $from = $request->string('from')->toString() ?: now()->startOfMonth()->toDateString();
        $to = $request->string('to')->toString() ?: now()->endOfMonth()->toDateString();

        return response()->json($monthly->build($from, $to));
    }

    /**
     * @return array{key: string, label: string, permission: PermissionName}
     */
    private function definition(Request $request, string $report): array
    {
        $definition = ReportCatalog::find($report);
        abort_unless($definition !== null, 404);
        abort_unless($request->user()?->can($definition['permission']->value), 403);

        return $definition;
    }

    /**
     * @return array{from: string, to: string, search: string, sort: string, direction: string, partner: string, account: string}
     */
    private function filters(Request $request): array
    {
        return [
            'from' => $request->string('from')->toString(),
            'to' => $request->string('to')->toString(),
            'search' => $request->string('search')->toString(),
            'sort' => $request->string('sort')->toString(),
            'direction' => $request->string('direction')->toString() ?: 'asc',
            'partner' => $request->string('partner')->toString(),
            'account' => $request->string('account')->toString(),
        ];
    }
}

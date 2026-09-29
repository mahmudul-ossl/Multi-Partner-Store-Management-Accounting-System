<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Http\Resources\PartnerResource;
use App\Models\Partner;
use App\Services\Finance\PartnerProfileService;
use App\Support\Format;
use App\Support\SimplePdf;
use App\Support\Spreadsheet;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PartnerProfileController extends Controller
{
    public function show(Request $request, Partner $partner, PartnerProfileService $profiles): Response
    {
        $this->authorizeProfile($request, $partner);

        $range = $this->range($request);
        $history = $profiles->history($partner, $range['from'], $range['to']);
        $partner->load('user:id,name,email');

        return Inertia::render('Partners/Profile', [
            'partner' => (new PartnerResource($partner))->resolve(),
            'summary' => $profiles->summary($partner),
            'history' => $this->page($history['lines'], max(1, (int) $request->integer('page', 1))),
            'totals' => [
                'opening' => $history['opening'],
                'debit_total' => $history['debit_total'],
                'credit_total' => $history['credit_total'],
                'closing' => $history['closing'],
            ],
            'filters' => [
                'from' => $range['from'] ?? '',
                'to' => $range['to'] ?? '',
            ],
        ]);
    }

    public function excel(Request $request, Partner $partner, PartnerProfileService $profiles): StreamedResponse
    {
        $this->authorizeProfile($request, $partner);

        $range = $this->range($request);
        $history = $profiles->history($partner, $range['from'], $range['to']);
        $summary = $profiles->summary($partner);
        $rows = [];

        foreach ($history['lines'] as $line) {
            $rows[] = [
                (string) $line['date'],
                (string) $line['reference'],
                (string) $line['type'],
                (string) $line['description'],
                (string) $line['account'],
                (string) $line['debit'],
                (string) $line['credit'],
                (string) $line['running_balance'],
            ];
        }

        $rows[] = ['Opening', $history['opening']['amount']];
        $rows[] = ['Debits', $history['debit_total']['amount']];
        $rows[] = ['Credits', $history['credit_total']['amount']];
        $rows[] = ['Closing', $history['closing']['amount']];
        $rows[] = ['Net capital', $summary['net_capital']['amount']];

        return Spreadsheet::download($this->filename($partner, 'xlsx'), [
            'Date',
            'Reference',
            'Type',
            'Description',
            'Account',
            'Debit',
            'Credit',
            'Running balance',
        ], $rows);
    }

    public function pdf(Request $request, Partner $partner, PartnerProfileService $profiles): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorizeProfile($request, $partner);

        $range = $this->range($request);
        $history = $profiles->history($partner, $range['from'], $range['to']);
        $summary = $profiles->summary($partner);
        $lines = [
            'Partner profile '.$partner->name.' ('.$partner->partner_code.')',
            'Date range: '.$this->rangeLabel($range),
            'Gross investment '.$summary['gross_investment']['formatted'].' ('.$summary['gross_investment']['amount'].')',
            'Withdrawals '.$summary['withdrawals']['formatted'].' ('.$summary['withdrawals']['amount'].')',
            'Allocated profit '.$summary['allocated_profit']['formatted'].' ('.$summary['allocated_profit']['amount'].')',
            'Promotion contribution '.$summary['promotion_contribution']['formatted'].' ('.$summary['promotion_contribution']['amount'].')',
            'Partner expenses '.$summary['partner_expenses']['formatted'].' ('.$summary['partner_expenses']['amount'].')',
            'Net capital '.$summary['net_capital']['formatted'].' ('.$summary['net_capital']['amount'].')',
            'Investment share '.$summary['investment_share_display'],
            $summary['investment_basis_label'],
            'Capital share '.$summary['capital_share_display'],
            $summary['capital_basis_label'],
        ];

        foreach ($history['lines'] as $line) {
            $lines[] = sprintf(
                '%s  %s  %s  Debit %s  Credit %s  Balance %s',
                $line['date'],
                $line['reference'],
                $line['type'],
                $line['debit'],
                $line['credit'],
                $line['running_balance'],
            );
            $lines[] = $line['description'].'  '.$line['account'];
        }

        $lines[] = 'Opening '.$history['opening']['formatted'].' ('.$history['opening']['amount'].')';
        $lines[] = 'Debits '.$history['debit_total']['formatted'].' ('.$history['debit_total']['amount'].')';
        $lines[] = 'Credits '.$history['credit_total']['formatted'].' ('.$history['credit_total']['amount'].')';
        $lines[] = 'Closing '.$history['closing']['formatted'].' ('.$history['closing']['amount'].')';
        $lines[] = 'Net capital '.$summary['net_capital']['formatted'].' ('.$summary['net_capital']['amount'].')';

        return SimplePdf::download($this->filename($partner, 'pdf'), $lines);
    }

    private function authorizeProfile(Request $request, Partner $partner): void
    {
        $this->authorize('view', $partner);
        abort_unless($request->user()?->can(PermissionName::PartnerStatementView->value), 403);
    }

    /**
     * @return array{from: ?string, to: ?string}
     */
    private function range(Request $request): array
    {
        $from = $this->date($request->string('from')->toString());
        $to = $this->date($request->string('to')->toString());

        if ($from !== null && $to !== null && $from > $to) {
            return ['from' => $to, 'to' => $from];
        }

        return ['from' => $from, 'to' => $to];
    }

    /**
     * @param  array{from: ?string, to: ?string}  $range
     */
    private function rangeLabel(array $range): string
    {
        if ($range['from'] === null && $range['to'] === null) {
            return 'all dates';
        }

        $from = $range['from'] === null ? 'the beginning' : (string) Format::date($range['from']);
        $to = $range['to'] === null ? 'the latest entry' : (string) Format::date($range['to']);

        return $from.' to '.$to;
    }

    private function date(string $value): ?string
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return $value;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    private function page(array $lines, int $page): array
    {
        $perPage = 15;
        $total = count($lines);

        return [
            'data' => array_slice($lines, ($page - 1) * $perPage, $perPage),
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
            'from' => $total === 0 ? null : (($page - 1) * $perPage) + 1,
            'to' => $total === 0 ? null : min($total, $page * $perPage),
            'total' => $total,
            'per_page' => $perPage,
        ];
    }

    private function filename(Partner $partner, string $extension): string
    {
        return 'partner-profile-'.$partner->partner_code.'.'.$extension;
    }
}

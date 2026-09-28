<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Partners\CreatePartner;
use App\Actions\Partners\DeletePartner;
use App\Actions\Partners\UpdatePartner;
use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Approvals\ApproveRequest;
use App\Http\Requests\Approvals\RejectRequest;
use App\Http\Requests\Finance\InvestmentRequest;
use App\Http\Requests\Finance\WithdrawalRequest;
use App\Http\Requests\Inventory\ProductRequest;
use App\Http\Requests\Inventory\PurchaseRequest;
use App\Http\Requests\Partners\StorePartnerRequest;
use App\Http\Requests\Partners\UpdatePartnerRequest;
use App\Http\Requests\Sales\SaleRequest;
use App\Models\ApprovalRequest;
use App\Models\Partner;
use App\Models\PartnerInvestment;
use App\Models\PartnerWithdrawal;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Services\Approvals\ApprovalDirectory;
use App\Services\Approvals\ApprovalService;
use App\Services\Finance\InvestmentService;
use App\Services\Finance\PartnerStatementService;
use App\Services\Finance\WithdrawalService;
use App\Services\Inventory\CatalogService;
use App\Services\Purchasing\PurchaseService;
use App\Services\Reports\MonthlyReport;
use App\Services\Sales\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    public function partners(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Partner::class);

        return response()->json(Partner::query()->orderBy('name')->get(['id', 'partner_code', 'name', 'status', 'ownership_percentage', 'investment_percentage']));
    }

    public function storePartner(StorePartnerRequest $request, CreatePartner $action): JsonResponse
    {
        $partner = $action->execute($request->validated());

        return response()->json($partner->only(['id', 'partner_code', 'name']), 201);
    }

    public function showPartner(Partner $partner): JsonResponse
    {
        $this->authorize('view', $partner);

        return response()->json($partner->only(['id', 'partner_code', 'name', 'phone', 'email', 'status', 'ownership_percentage', 'investment_percentage']));
    }

    public function updatePartner(UpdatePartnerRequest $request, Partner $partner, UpdatePartner $action): JsonResponse
    {
        $partner = $action->execute($partner, $request->validated());

        return response()->json($partner->only(['id', 'partner_code', 'name']));
    }

    public function destroyPartner(Partner $partner, DeletePartner $action): JsonResponse
    {
        $this->authorize('delete', $partner);
        $action->execute($partner);

        return response()->json(['message' => 'Partner archived.']);
    }

    public function investments(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PartnerInvestment::class);

        return response()->json(PartnerInvestment::query()->latest('transaction_date')->limit(50)->get(['id', 'reference', 'partner_id', 'amount', 'transaction_date', 'status']));
    }

    public function storeInvestment(InvestmentRequest $request, InvestmentService $investments): JsonResponse
    {
        $investment = $investments->create($request->user(), $request->validated());

        return response()->json([
            'id' => $investment->id,
            'reference' => $investment->reference,
            'status' => $investment->status->value,
            'approval_id' => $investment->approvalRequest?->id,
        ], 201);
    }

    public function withdrawals(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PartnerWithdrawal::class);

        return response()->json(PartnerWithdrawal::query()->latest('transaction_date')->limit(50)->get(['id', 'reference', 'partner_id', 'amount', 'transaction_date', 'status']));
    }

    public function storeWithdrawal(WithdrawalRequest $request, WithdrawalService $withdrawals): JsonResponse
    {
        $withdrawal = $withdrawals->create($request->user(), $request->validated());

        return response()->json([
            'id' => $withdrawal->id,
            'reference' => $withdrawal->reference,
            'status' => $withdrawal->status->value,
            'approval_id' => $withdrawal->approvalRequest?->id,
        ], 201);
    }

    public function statement(Request $request, Partner $partner, PartnerStatementService $statements): JsonResponse
    {
        $this->authorize('view', $partner);

        return response()->json([
            'position' => $statements->position($partner),
            'lines' => $statements->statement($partner),
        ]);
    }

    public function approvals(Request $request, ApprovalDirectory $directory): JsonResponse
    {
        $this->authorize('viewAny', ApprovalRequest::class);

        return response()->json($directory->actionable($request->user(), [])->items());
    }

    public function approve(ApproveRequest $request, ApprovalRequest $approval, ApprovalService $approvals): JsonResponse
    {
        $approvals->approve($approval, $request->user(), $request->validated('comment'));

        return response()->json(['status' => $approval->fresh()->status->value]);
    }

    public function reject(RejectRequest $request, ApprovalRequest $approval, ApprovalService $approvals): JsonResponse
    {
        $approvals->reject($approval, $request->user(), (string) $request->validated('comment'));

        return response()->json(['status' => $approval->fresh()->status->value]);
    }

    public function products(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        return response()->json(Product::query()->orderBy('name')->get(['id', 'sku', 'name', 'selling_price', 'status']));
    }

    public function storeProduct(ProductRequest $request, CatalogService $catalog): JsonResponse
    {
        $product = $catalog->createProduct($request->user(), $request->validated());

        return response()->json($product->only(['id', 'sku', 'name']), 201);
    }

    public function purchases(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Purchase::class);

        return response()->json(Purchase::query()->latest('transaction_date')->limit(50)->get(['id', 'reference', 'supplier_id', 'total', 'due_amount', 'status']));
    }

    public function storePurchase(PurchaseRequest $request, PurchaseService $purchases): JsonResponse
    {
        $purchase = $purchases->create($request->user(), $request->validated());

        return response()->json([
            'id' => $purchase->id,
            'reference' => $purchase->reference,
            'status' => $purchase->status->value,
        ], 201);
    }

    public function sales(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Sale::class);

        return response()->json(Sale::query()->latest('transaction_date')->limit(50)->get(['id', 'reference', 'customer_id', 'total', 'due_amount', 'status']));
    }

    public function storeSale(SaleRequest $request, SaleService $sales): JsonResponse
    {
        $sale = $sales->create($request->user(), $request->validated());

        return response()->json([
            'id' => $sale->id,
            'reference' => $sale->reference,
            'status' => $sale->status->value,
        ], 201);
    }

    public function monthly(Request $request, MonthlyReport $monthly): JsonResponse
    {
        abort_unless($request->user()?->can(PermissionName::ReportView->value), 403);
        $from = $request->string('from')->toString() ?: now()->startOfMonth()->toDateString();
        $to = $request->string('to')->toString() ?: now()->endOfMonth()->toDateString();

        return response()->json($monthly->build($from, $to));
    }
}

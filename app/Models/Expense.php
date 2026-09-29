<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Models\Concerns\HasApprovalRequest;
use App\Models\Concerns\RejectsDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasApprovalRequest, RejectsDeletion;

    protected $fillable = [
        'reference',
        'category',
        'amount',
        'transaction_date',
        'payment_method',
        'financial_account_id',
        'partner_id',
        'description',
        'status',
        'created_by',
        'journal_entry_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ExpenseCategory::class,
            'amount' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'transaction_date' => 'date',
            'status' => DocumentStatus::class,
        ];
    }

    public function deletionMessage(): string
    {
        return 'Expenses cannot be deleted. Reject them instead.';
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}

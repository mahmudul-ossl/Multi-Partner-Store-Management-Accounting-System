<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\PaymentMethod;
use App\Enums\SalesIncomeSource;
use App\Models\Concerns\HasApprovalRequest;
use App\Models\Concerns\RejectsDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesIncome extends Model
{
    use HasApprovalRequest, RejectsDeletion;

    protected $fillable = [
        'reference',
        'source',
        'amount',
        'transaction_date',
        'payment_method',
        'financial_account_id',
        'note',
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
            'source' => SalesIncomeSource::class,
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
            'payment_method' => PaymentMethod::class,
            'status' => DocumentStatus::class,
        ];
    }

    public function deletionMessage(): string
    {
        return 'Sales income cannot be deleted. Reject it instead.';
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

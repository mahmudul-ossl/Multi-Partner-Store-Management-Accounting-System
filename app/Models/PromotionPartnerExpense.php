<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\FundingSource;
use App\Enums\PaymentMethod;
use App\Models\Concerns\HasApprovalRequest;
use App\Models\Concerns\RejectsDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionPartnerExpense extends Model
{
    use HasApprovalRequest, RejectsDeletion;

    protected $fillable = [
        'reference',
        'promotion_id',
        'partner_id',
        'amount',
        'funded_by',
        'payment_method',
        'financial_account_id',
        'transaction_date',
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
            'amount' => 'decimal:2',
            'funded_by' => FundingSource::class,
            'payment_method' => PaymentMethod::class,
            'transaction_date' => 'date',
            'status' => DocumentStatus::class,
        ];
    }

    public function deletionMessage(): string
    {
        return 'Promotion contributions cannot be deleted. Reject or reverse them.';
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
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

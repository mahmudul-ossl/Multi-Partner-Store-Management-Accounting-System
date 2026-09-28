<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AllocationMethod;
use App\Enums\DocumentStatus;
use App\Models\Concerns\HasApprovalRequest;
use App\Models\Concerns\RejectsDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProfitAllocation extends Model
{
    use HasApprovalRequest, RejectsDeletion;

    protected $fillable = [
        'reference',
        'method',
        'amount',
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
            'method' => AllocationMethod::class,
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
            'status' => DocumentStatus::class,
        ];
    }

    public function deletionMessage(): string
    {
        return 'Profit allocations cannot be deleted. Reject them instead.';
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProfitAllocationLine::class);
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

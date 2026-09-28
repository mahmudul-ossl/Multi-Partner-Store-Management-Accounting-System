<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RejectsDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfitAllocationLine extends Model
{
    use RejectsDeletion;

    protected $fillable = ['profit_allocation_id', 'partner_id', 'percentage', 'amount'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'percentage' => 'decimal:4',
            'amount' => 'decimal:2',
        ];
    }

    public function deletionMessage(): string
    {
        return 'Allocation lines cannot be deleted.';
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(ProfitAllocation::class, 'profit_allocation_id');
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FinancialAccountType;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialAccount extends Model
{
    protected $fillable = [
        'name',
        'type',
        'chart_of_account_id',
        'opening_balance',
        'current_balance',
        'is_active',
        'is_system',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FinancialAccountType::class,
            'opening_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function applyMovement(string $debit, string $credit): void
    {
        $delta = Money::of($debit)->sub($credit)->amount();
        $this->current_balance = Money::of((string) $this->current_balance)->add($delta)->amount();
        $this->save();
    }
}

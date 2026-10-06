<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PromotionPlatform;
use App\Enums\PromotionStatus;
use App\Models\Concerns\RejectsDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promotion extends Model
{
    use RejectsDeletion;

    protected $fillable = [
        'name',
        'platform',
        'starts_on',
        'ends_on',
        'budget',
        'actual_amount',
        'status',
        'description',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => PromotionPlatform::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'budget' => 'decimal:2',
            'actual_amount' => 'decimal:2',
            'status' => PromotionStatus::class,
        ];
    }

    public function deletionMessage(): string
    {
        return 'Promotions cannot be deleted.';
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(PromotionPartnerExpense::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApprovalRequestType;
use App\Enums\ApprovalStatus;
use App\Models\Concerns\RejectsDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalRequest extends Model
{
    use RejectsDeletion;

    protected $fillable = [
        'request_type',
        'reference_type',
        'reference_id',
        'requested_by',
        'status',
        'current_step',
        'required_approvals',
        'completed_approvals',
        'amount',
        'requested_at',
        'approved_at',
        'rejected_at',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_type' => ApprovalRequestType::class,
            'status' => ApprovalStatus::class,
            'current_step' => 'integer',
            'required_approvals' => 'integer',
            'completed_approvals' => 'integer',
            'amount' => 'decimal:2',
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class);
    }
}

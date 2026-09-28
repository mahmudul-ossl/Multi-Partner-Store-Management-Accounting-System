<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApprovalRequestType;
use Illuminate\Database\Eloquent\Model;

class ApprovalThreshold extends Model
{
    protected $fillable = [
        'request_type',
        'min_amount',
        'max_amount',
        'required_approvals',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_type' => ApprovalRequestType::class,
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'required_approvals' => 'integer',
        ];
    }
}

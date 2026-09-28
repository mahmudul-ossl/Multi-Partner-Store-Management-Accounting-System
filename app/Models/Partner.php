<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PartnerStatus;
use Database\Factories\PartnerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Partner extends Model
{
    /** @use HasFactory<PartnerFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'partner_code',
        'name',
        'phone',
        'email',
        'address',
        'joining_date',
        'ownership_percentage',
        'investment_percentage',
        'status',
        'user_id',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joining_date' => 'date',
            'ownership_percentage' => 'decimal:4',
            'investment_percentage' => 'decimal:4',
            'status' => PartnerStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Attributes recorded on the audit log. Ownership and investment stay
     * separate columns and are both captured.
     *
     * @return array<string, mixed>
     */
    public function auditSnapshot(): array
    {
        return [
            'partner_code' => $this->partner_code,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'joining_date' => $this->joining_date?->toDateString(),
            'ownership_percentage' => $this->ownership_percentage,
            'investment_percentage' => $this->investment_percentage,
            'status' => $this->status instanceof PartnerStatus ? $this->status->value : $this->status,
            'user_id' => $this->user_id,
            'notes' => $this->notes,
        ];
    }
}

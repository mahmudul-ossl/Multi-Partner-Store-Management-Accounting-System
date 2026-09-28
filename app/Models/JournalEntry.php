<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Models\Concerns\RejectsDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class JournalEntry extends Model
{
    use RejectsDeletion;

    protected $fillable = [
        'entry_date',
        'reference',
        'source_type',
        'source_id',
        'description',
        'status',
        'created_by',
        'reversal_of',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'status' => DocumentStatus::class,
        ];
    }

    public function deletionMessage(): string
    {
        return 'Journal entries cannot be deleted. Post a reversal.';
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversedEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of');
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reversal_of');
    }
}

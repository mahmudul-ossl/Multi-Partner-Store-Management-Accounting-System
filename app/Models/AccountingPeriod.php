<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RejectsDeletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingPeriod extends Model
{
    use RejectsDeletion;

    protected $fillable = ['closed_through', 'note', 'closed_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['closed_through' => 'date'];
    }

    public function deletionMessage(): string
    {
        return 'A closed period cannot be deleted.';
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}

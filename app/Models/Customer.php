<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomerStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = ['name', 'phone', 'email', 'address', 'status', 'notes'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => CustomerStatus::class];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}

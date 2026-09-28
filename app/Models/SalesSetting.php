<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesSetting extends Model
{
    protected $fillable = ['large_discount_threshold'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['large_discount_threshold' => 'decimal:2'];
    }
}

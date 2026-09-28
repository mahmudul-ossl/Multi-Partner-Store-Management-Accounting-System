<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Money;
use Tests\TestCase;

class MoneyFormatTest extends TestCase
{
    public function test_money_formats_like_bangladeshi_taka(): void
    {
        $this->assertSame('৳100,000.00', Money::of('100000')->formatted());
        $this->assertSame('৳100,000.50', Money::of('100000.5')->formatted());
        $this->assertSame('-৳1,250.50', Money::of('-1250.5')->formatted());
    }
}

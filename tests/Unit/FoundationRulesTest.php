<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\SelfApprovalException;
use App\Exceptions\UnbalancedEntryException;
use App\Services\Approvals\SelfApprovalGuard;
use App\Services\Ledger\BalancedEntry;
use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FoundationRulesTest extends TestCase
{
    public function test_money_rejects_floats(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::of(10.5);
    }

    public function test_money_keeps_two_decimal_places(): void
    {
        $this->assertSame('100000.00', Money::of('100000')->amount());
        $this->assertSame('100000.50', Money::of('100000.5')->amount());
    }

    public function test_a_user_cannot_approve_their_own_request(): void
    {
        $this->expectException(SelfApprovalException::class);
        SelfApprovalGuard::assertNotSelf(4, 4);
    }

    public function test_self_approval_guard_allows_a_different_user(): void
    {
        SelfApprovalGuard::assertNotSelf(4, 9);
        $this->assertTrue(true);
    }

    public function test_journal_lines_must_balance(): void
    {
        BalancedEntry::assertBalanced([
            ['debit' => '500000.00', 'credit' => '0'],
            ['debit' => '0.00', 'credit' => '500000'],
        ]);

        $this->expectException(UnbalancedEntryException::class);
        BalancedEntry::assertBalanced([
            ['debit' => '10.00', 'credit' => '0.00'],
            ['debit' => '0.00', 'credit' => '9.00'],
        ]);
    }

    public function test_a_journal_line_cannot_hold_both_sides(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BalancedEntry::assertBalanced([
            ['debit' => '10.00', 'credit' => '10.00'],
        ]);
    }
}

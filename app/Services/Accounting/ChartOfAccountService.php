<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Enums\AccountType;
use App\Enums\AuditAction;
use App\Enums\NormalBalance;
use App\Exceptions\ApprovalStateException;
use App\Models\ChartOfAccount;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

final class ChartOfAccountService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * @param  array{code: string, name: string, type: string, normal_balance: string, parent_id?: int|null, is_active?: bool, description?: string|null}  $attributes
     */
    public function create(User $actor, array $attributes): ChartOfAccount
    {
        return DB::transaction(function () use ($actor, $attributes): ChartOfAccount {
            $type = AccountType::from($attributes['type']);
            $parentId = $this->nullableInt($attributes['parent_id'] ?? null);
            $this->assertParent(null, $parentId, $type);

            $account = ChartOfAccount::query()->create([
                'code' => $attributes['code'],
                'name' => $attributes['name'],
                'type' => $type,
                'normal_balance' => NormalBalance::from($attributes['normal_balance']),
                'parent_id' => $parentId,
                'is_active' => (bool) ($attributes['is_active'] ?? true),
                'is_system' => false,
                'description' => $attributes['description'] ?? null,
            ]);

            $this->audit->record(AuditAction::Created, $account, null, $this->snapshot($account), $actor);

            return $account;
        });
    }

    /**
     * @param  array{code: string, name: string, type: string, normal_balance: string, parent_id?: int|null, is_active?: bool, description?: string|null}  $attributes
     */
    public function update(ChartOfAccount $account, User $actor, array $attributes): ChartOfAccount
    {
        return DB::transaction(function () use ($account, $actor, $attributes): ChartOfAccount {
            $account = ChartOfAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $before = $this->snapshot($account);

            $type = AccountType::from($attributes['type']);
            $normal = NormalBalance::from($attributes['normal_balance']);
            $parentId = $this->nullableInt($attributes['parent_id'] ?? null);
            $isActive = array_key_exists('is_active', $attributes)
                ? (bool) $attributes['is_active']
                : (bool) $account->is_active;

            if ($account->is_system) {
                $this->assertUnchanged('code', $account->code, $attributes['code']);
                $this->assertUnchanged('type', $account->type->value, $type->value);
                $this->assertUnchanged('normal balance', $account->normal_balance->value, $normal->value);
                $this->assertUnchanged('parent', $account->parent_id, $parentId);
                $this->assertUnchanged('active flag', (bool) $account->is_active, $isActive);
            } else {
                $locked = $account->lines()->exists()
                    || $account->children()->exists()
                    || $account->financialAccounts()->exists();

                if ($locked && ($account->code !== $attributes['code'] || $account->type !== $type)) {
                    throw new ApprovalStateException('Accounts with history or child accounts cannot change code or type.');
                }

                $this->assertParent($account, $parentId, $type);
            }

            $account->fill([
                'code' => $account->is_system ? $account->code : $attributes['code'],
                'name' => $attributes['name'],
                'type' => $account->is_system ? $account->type : $type,
                'normal_balance' => $account->is_system ? $account->normal_balance : $normal,
                'parent_id' => $account->is_system ? $account->parent_id : $parentId,
                'is_active' => $account->is_system ? $account->is_active : $isActive,
                'description' => $attributes['description'] ?? null,
            ]);
            $account->save();

            $this->audit->record(AuditAction::Updated, $account, $before, $this->snapshot($account), $actor);

            return $account;
        });
    }

    public function delete(ChartOfAccount $account, User $actor): void
    {
        DB::transaction(function () use ($account, $actor): void {
            $account = ChartOfAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();

            if ($account->is_system) {
                throw new ApprovalStateException('System accounts cannot be deleted.');
            }

            if ($account->lines()->exists() || $account->children()->exists() || $account->financialAccounts()->exists()) {
                throw new ApprovalStateException('Accounts with history or child accounts cannot be deleted.');
            }

            $before = $this->snapshot($account);
            $account->delete();
            $this->audit->record(AuditAction::Deleted, $account, $before, null, $actor);
        });
    }

    private function assertUnchanged(string $label, mixed $current, mixed $incoming): void
    {
        if ($current != $incoming) {
            throw new ApprovalStateException('System account '.$label.' cannot be changed.');
        }
    }

    private function assertParent(?ChartOfAccount $account, ?int $parentId, AccountType $type): void
    {
        if ($parentId === null) {
            return;
        }

        if ($account instanceof ChartOfAccount && $account->id === $parentId) {
            throw new ApprovalStateException('An account cannot be its own parent.');
        }

        $parent = ChartOfAccount::query()->find($parentId);

        if (! $parent instanceof ChartOfAccount) {
            throw new ApprovalStateException('The parent account does not exist.');
        }

        if ($parent->type !== $type) {
            throw new ApprovalStateException('A child account must use the same type as its parent.');
        }

        $cursor = $parent;
        $guard = 0;

        while ($cursor instanceof ChartOfAccount && $guard < 30) {
            if ($account instanceof ChartOfAccount && $cursor->id === $account->id) {
                throw new ApprovalStateException('That parent would create a cycle.');
            }

            $cursor = $cursor->parent;
            $guard++;
        }
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(ChartOfAccount $account): array
    {
        return [
            'code' => $account->code,
            'name' => $account->name,
            'type' => $account->type->value,
            'normal_balance' => $account->normal_balance->value,
            'parent_id' => $account->parent_id,
            'is_active' => $account->is_active,
            'is_system' => $account->is_system,
            'description' => $account->description,
        ];
    }
}

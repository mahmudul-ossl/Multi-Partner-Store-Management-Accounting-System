<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Enums\AuditAction;
use App\Enums\CustomerStatus;
use App\Enums\DocumentStatus;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use App\Services\AuditLogService;
use App\Support\Money;

final class CustomerService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * @param  array{name: string, phone?: string|null, email?: string|null, address?: string|null, notes?: string|null}  $attributes
     */
    public function create(User $actor, array $attributes): Customer
    {
        $customer = Customer::query()->create([
            'name' => $attributes['name'],
            'phone' => $attributes['phone'] ?? null,
            'email' => $attributes['email'] ?? null,
            'address' => $attributes['address'] ?? null,
            'status' => CustomerStatus::Active,
            'notes' => $attributes['notes'] ?? null,
        ]);
        $this->audit->record(AuditAction::Created, $customer, null, ['name' => $customer->name], $actor);

        return $customer;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function dues(): array
    {
        return Customer::query()->orderBy('name')->get()->map(function (Customer $customer): array {
            $due = '0.00';

            foreach (Sale::query()->where('customer_id', $customer->id)->where('status', DocumentStatus::Completed)->pluck('due_amount') as $amount) {
                $due = Money::of($due)->add((string) $amount)->amount();
            }

            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'due' => Money::of($due)->formatted(),
                'due_amount' => $due,
            ];
        })->all();
    }
}

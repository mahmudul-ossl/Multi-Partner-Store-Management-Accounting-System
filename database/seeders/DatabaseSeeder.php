<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PartnerStatus;
use App\Enums\RoleName;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleAndPermissionSeeder::class);

        $this->upsertUser(
            name: (string) config('mpstore.seed.super_admin_name'),
            email: (string) config('mpstore.seed.super_admin_email'),
            password: $this->requiredSecret('mpstore.seed.super_admin_password', 'SEED_SUPER_ADMIN_PASSWORD'),
            phone: '01710000001',
            role: RoleName::SuperAdmin,
        );

        $partners = [
            [
                'code' => 'P-0001',
                'name' => 'Sirajul Islam',
                'phone' => '01711000001',
                'email' => 'sirajul.islam@mpstore.test',
                'address' => null,
                'joining_date' => now()->toDateString(),
                'ownership' => '50.0000',
                'investment' => '50.0000',
                'status' => PartnerStatus::Active,
                'notes' => null,
            ],
            [
                'code' => 'P-0002',
                'name' => 'Mahmudul Hasan',
                'phone' => '01711000002',
                'email' => 'mahmudul.hasan@mpstore.test',
                'address' => null,
                'joining_date' => now()->toDateString(),
                'ownership' => '50.0000',
                'investment' => '50.0000',
                'status' => PartnerStatus::Active,
                'notes' => null,
            ],
        ];

        foreach ($partners as $partner) {
            Partner::query()->updateOrCreate(
                ['partner_code' => $partner['code']],
                [
                    'name' => $partner['name'],
                    'phone' => $partner['phone'],
                    'email' => $partner['email'],
                    'address' => $partner['address'],
                    'joining_date' => $partner['joining_date'],
                    'ownership_percentage' => $partner['ownership'],
                    'investment_percentage' => $partner['investment'],
                    'status' => $partner['status'],
                    'user_id' => null,
                    'notes' => $partner['notes'],
                ],
            );
        }

        $this->call([
            ChartOfAccountSeeder::class,
            FinancialAccountSeeder::class,
            ApprovalThresholdSeeder::class,
        ]);
    }

    private function upsertUser(string $name, string $email, string $password, string $phone, RoleName $role): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'phone' => $phone,
                'password' => $password,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $user->syncRoles([$role->value]);

        return $user;
    }

    private function requiredSecret(string $configKey, string $envName): string
    {
        $value = config($configKey);

        if (! is_string($value) || $value === '') {
            throw new RuntimeException("Set {$envName} before seeding. Passwords are not hard-coded.");
        }

        return $value;
    }
}

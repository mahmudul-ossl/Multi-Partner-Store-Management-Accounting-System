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

        $superAdmin = $this->upsertUser(
            name: (string) config('mpstore.seed.super_admin_name'),
            email: (string) config('mpstore.seed.super_admin_email'),
            password: $this->requiredSecret('mpstore.seed.super_admin_password', 'SEED_SUPER_ADMIN_PASSWORD'),
            phone: '01710000001',
            role: RoleName::SuperAdmin,
        );

        $demoPassword = $this->requiredSecret('mpstore.seed.demo_password', 'SEED_DEMO_PASSWORD');

        $staff = [
            ['System Admin', 'admin@mpstore.test', '01710000002', RoleName::Admin],
            ['Nasreen Chowdhury', 'accountant@mpstore.test', '01710000003', RoleName::Accountant],
            ['Imran Kabir', 'inventory@mpstore.test', '01710000004', RoleName::InventoryManager],
            ['Sadia Rahman', 'sales@mpstore.test', '01710000005', RoleName::SalesManager],
            ['Viewer Account', 'viewer@mpstore.test', '01710000006', RoleName::Viewer],
        ];

        foreach ($staff as [$name, $email, $phone, $role]) {
            $this->upsertUser($name, $email, $demoPassword, $phone, $role);
        }

        $partners = [
            [
                'code' => 'P-0001',
                'name' => 'Rahim Uddin',
                'phone' => '01711111101',
                'email' => 'rahim.uddin@mpstore.test',
                'address' => 'House 12, Road 4, Dhanmondi, Dhaka',
                'joining_date' => '2023-01-15',
                'ownership' => '18.0000',
                'investment' => '20.0000',
                'status' => PartnerStatus::Active,
                'user' => ['Rahim Uddin', 'rahim.uddin@mpstore.test', '01711111101'],
                'notes' => 'Founding partner. Ownership and investment shares differ by agreement.',
            ],
            [
                'code' => 'P-0002',
                'name' => 'Fatema Akter',
                'phone' => '01812222202',
                'email' => 'fatema.akter@mpstore.test',
                'address' => 'Apartment 5B, GEC Circle, Chattogram',
                'joining_date' => '2023-03-02',
                'ownership' => '15.0000',
                'investment' => '12.0000',
                'status' => PartnerStatus::Active,
                'user' => ['Fatema Akter', 'fatema.akter@mpstore.test', '01812222202'],
                'notes' => null,
            ],
            [
                'code' => 'P-0003',
                'name' => 'Karim Hossain',
                'phone' => '01913333303',
                'email' => 'karim.hossain@mpstore.test',
                'address' => 'Zindabazar, Sylhet',
                'joining_date' => '2023-06-20',
                'ownership' => '12.0000',
                'investment' => '15.0000',
                'status' => PartnerStatus::Active,
                'user' => ['Karim Hossain', 'karim.hossain@mpstore.test', '01913333303'],
                'notes' => 'Handles supplier introductions in Sylhet.',
            ],
            [
                'code' => 'P-0004',
                'name' => 'Nusrat Jahan',
                'phone' => '01614444404',
                'email' => 'nusrat.jahan@mpstore.test',
                'address' => 'Shaheb Bazar, Rajshahi',
                'joining_date' => '2024-01-10',
                'ownership' => '10.0000',
                'investment' => '10.0000',
                'status' => PartnerStatus::Active,
                'user' => ['Nusrat Jahan', 'nusrat.jahan@mpstore.test', '01614444404'],
                'notes' => null,
            ],
            [
                'code' => 'P-0005',
                'name' => 'Shahidul Islam',
                'phone' => '01515555505',
                'email' => 'shahidul.islam@mpstore.test',
                'address' => 'Khalishpur, Khulna',
                'joining_date' => '2024-04-18',
                'ownership' => '10.0000',
                'investment' => '8.0000',
                'status' => PartnerStatus::Active,
                'user' => null,
                'notes' => 'No system login yet.',
            ],
            [
                'code' => 'P-0006',
                'name' => 'Ayesha Siddiqua',
                'phone' => '01316666606',
                'email' => 'ayesha.siddiqua@mpstore.test',
                'address' => 'Cantonment, Barishal',
                'joining_date' => '2024-08-01',
                'ownership' => '8.0000',
                'investment' => '10.0000',
                'status' => PartnerStatus::Inactive,
                'user' => ['Ayesha Siddiqua', 'ayesha.siddiqua@mpstore.test', '01316666606'],
                'notes' => 'Temporarily inactive. Capital share remains on record.',
            ],
            [
                'code' => 'P-0007',
                'name' => 'Tanvir Ahmed',
                'phone' => '01717777707',
                'email' => 'tanvir.ahmed@mpstore.test',
                'address' => 'Station Road, Mymensingh',
                'joining_date' => '2025-02-11',
                'ownership' => '15.0000',
                'investment' => '15.0000',
                'status' => PartnerStatus::Suspended,
                'user' => null,
                'notes' => 'Suspended pending a partnership review.',
            ],
            [
                'code' => 'P-0008',
                'name' => 'Mahmuda Khatun',
                'phone' => '01818888808',
                'email' => 'mahmuda.khatun@mpstore.test',
                'address' => 'College Road, Rangpur',
                'joining_date' => '2025-11-05',
                'ownership' => '12.0000',
                'investment' => '10.0000',
                'status' => PartnerStatus::Active,
                'user' => null,
                'notes' => null,
            ],
        ];

        foreach ($partners as $partner) {
            $userId = null;

            if (is_array($partner['user'])) {
                $account = $this->upsertUser(
                    $partner['user'][0],
                    $partner['user'][1],
                    $demoPassword,
                    $partner['user'][2],
                    RoleName::Partner,
                );
                $userId = $account->id;
            }

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
                    'user_id' => $userId,
                    'notes' => $partner['notes'],
                ],
            );
        }

        unset($superAdmin);

        $this->call([
            ChartOfAccountSeeder::class,
            FinancialAccountSeeder::class,
            ApprovalThresholdSeeder::class,
            PartnerFinanceExampleSeeder::class,
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

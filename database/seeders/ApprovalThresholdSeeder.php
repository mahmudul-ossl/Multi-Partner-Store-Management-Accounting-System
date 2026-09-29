<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ApprovalRequestType;
use App\Models\ApprovalThreshold;
use Illuminate\Database\Seeder;

class ApprovalThresholdSeeder extends Seeder
{
    public function run(): void
    {
        $bands = [
            ['0.00', '10000.00', 1],
            ['10000.01', '100000.00', 2],
            ['100000.01', null, 3],
        ];

        foreach (ApprovalRequestType::cases() as $type) {
            foreach ($bands as [$min, $max, $required]) {
                ApprovalThreshold::query()->updateOrCreate(
                    [
                        'request_type' => $type,
                        'min_amount' => $min,
                    ],
                    [
                        'max_amount' => $max,
                        'required_approvals' => $required,
                    ],
                );
            }
        }
    }
}

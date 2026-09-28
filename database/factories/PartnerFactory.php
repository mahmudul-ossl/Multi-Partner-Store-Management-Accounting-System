<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PartnerStatus;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Partner>
 */
class PartnerFactory extends Factory
{
    protected $model = Partner::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'partner_code' => 'P-'.fake()->unique()->numerify('####'),
            'name' => fake()->name(),
            'phone' => '017'.fake()->numerify('########'),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->streetAddress().', Dhaka',
            'joining_date' => fake()->date(),
            'ownership_percentage' => '10.0000',
            'investment_percentage' => '10.0000',
            'status' => PartnerStatus::Active,
            'user_id' => null,
            'notes' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => PartnerStatus::Inactive,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => [
            'status' => PartnerStatus::Suspended,
        ]);
    }
}

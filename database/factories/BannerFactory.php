<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BannerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => 'default',
            'message' => fake()->sentence(),
            'is_active' => true,
            'start_at' => null,
            'end_at' => null,
            'event_at' => null,
            'details_url' => null,
        ];
    }
}

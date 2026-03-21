<?php

namespace Database\Factories;

use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Item>
 */
class ItemFactory extends Factory
{
    protected $model = Item::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'game_id' => $this->faker->unique()->numberBetween(1, 100000),
            'name' => $name,
            'slug' => str_replace(' ', '_', $name),
            'cost' => $this->faker->numberBetween(1, 10000),
            'is_active' => true,
            'is_listable' => true,
            'search_aliases' => null,
        ];
    }

    public function withAliases(array $aliases): static
    {
        return $this->state(fn (array $attributes) => [
            'search_aliases' => $aliases,
        ]);
    }
}

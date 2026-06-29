<?php

namespace Database\Factories;

use App\Enums\LineSense;
use App\Models\Line;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Line>
 */
class LineFactory extends Factory
{
    protected $model = Line::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('??-####'),
            'name' => fake()->streetName(),
            'color' => fake()->hexColor(),
            'geo_json' => [
                'type' => 'MultiLineString',
                'coordinates' => [
                    [
                        [fake()->longitude(-63.3, -63.1), fake()->latitude(-17.9, -17.7)],
                        [fake()->longitude(-63.3, -63.1), fake()->latitude(-17.9, -17.7)],
                    ],
                ],
            ],
            'sense' => fake()->randomElement(LineSense::cases()),
            'syndicate' => (string) fake()->numberBetween(1, 10),
            'objectid' => fake()->unique()->numberBetween(1, 10000),
            'average_rating' => fake()->optional(0.7)->randomFloat(2, 1, 5),
            'total_reviews' => fake()->numberBetween(0, 50),
        ];
    }

    public function outbound(): static
    {
        return $this->state(fn () => ['sense' => LineSense::Outbound]);
    }

    public function return(): static
    {
        return $this->state(fn () => ['sense' => LineSense::Return]);
    }
}

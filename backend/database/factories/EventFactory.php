<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $starts = Carbon::instance(fake()->dateTimeBetween('+1 week', '+3 months'));
        $ends = $starts->copy()->addHours(fake()->numberBetween(2, 8));

        return [
            'organizer_id' => User::factory()->organizer(),
            'title' => fake()->sentence(rand(3, 6)),
            'description' => collect(fake()->paragraphs(rand(2, 5)))->implode("\n\n"),
            'starts_at' => $starts,
            'ends_at' => $ends,
            'capacity' => fake()->numberBetween(20, 200),
            'status' => EventStatus::Published,
            'metadata' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EventStatus::Published,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EventStatus::Cancelled,
        ]);
    }

    public function inThePast(): static
    {
        return $this->state(function (array $attributes) {
            $starts = Carbon::instance(fake()->dateTimeBetween('-8 months', '-2 days'));
            $ends = $starts->copy()->addHours(fake()->numberBetween(2, 6));

            return [
                'starts_at' => $starts,
                'ends_at' => $ends,
            ];
        });
    }

    public function inTheFuture(): static
    {
        return $this->state(function (array $attributes) {
            $starts = Carbon::instance(fake()->dateTimeBetween('+1 week', '+4 months'));
            $ends = $starts->copy()->addHours(fake()->numberBetween(2, 8));

            return [
                'starts_at' => $starts,
                'ends_at' => $ends,
            ];
        });
    }
}

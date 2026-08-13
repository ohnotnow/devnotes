<?php

namespace Database\Factories;

use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Note>
 */
class NoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'body' => '## '.fake()->sentence(3)."\n\n"
                .fake()->paragraph()."\n\n"
                ."See the [docs](https://example.com/docs) for more.\n\n"
                .fake()->paragraph(),
            'user_id' => User::factory(),
        ];
    }
}

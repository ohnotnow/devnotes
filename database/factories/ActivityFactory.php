<?php

namespace Database\Factories;

use App\Enums\ActivityAction;
use App\Models\Activity;
use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'note_id' => Note::factory(),
            'action' => ActivityAction::Created,
            'description' => function (array $attributes) {
                $note = Note::find($attributes['note_id']);

                return "{$attributes['action']->value} note #{$note->code} '{$note->title}'";
            },
        ];
    }
}

<?php

namespace Database\Seeders;

use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        [$adminUser, $standardUser] = $this->createUsers();
        $this->createNotes($adminUser, $standardUser);
    }

    private function createUsers(): array
    {
        $adminUser = User::factory()->create([
            'username' => 'admin2x',
            'email' => 'admin2x@example.test',
            'password' => bcrypt('secret'),
            'is_admin' => true,
            'forenames' => 'Jenny',
            'surname' => 'MacAdmin',
        ]);

        $standardUser = User::factory()->create([
            'username' => 'user2x',
            'email' => 'user2x@example.test',
            'password' => bcrypt('secret'),
            'is_admin' => false,
            'forenames' => 'Olivia',
            'surname' => 'McUser',
        ]);

        return [$adminUser, $standardUser];
    }

    private function createNotes(User $adminUser, User $standardUser): void
    {
        $firstNote = Note::factory()->create([
            'user_id' => $adminUser->id,
            'title' => 'Livewire flyout modals lose focus on close',
            'body' => "If a `flux:modal variant=\"flyout\"` is closed via the escape key, focus does not always return to the trigger button.\n\nWorkaround is to set `wire:key` on the trigger. See the [Flux docs](https://fluxui.dev/components/modal) for the modal API.",
        ]);

        Note::factory()->create([
            'user_id' => $standardUser->id,
            'title' => 'More flyout modal focus history',
            'body' => "Related to the escape-key problem - see note #{$firstNote->id} for the original write-up.\n\nTabbing out of the last field also skips the close button on some browsers.",
        ]);

        Note::factory(3)->create(['user_id' => $standardUser->id]);
    }
}

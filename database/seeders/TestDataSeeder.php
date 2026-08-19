<?php

namespace Database\Seeders;

use App\Enums\ActivityAction;
use App\Models\Activity;
use App\Models\Note;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        [$adminUser, $standardUser] = $this->createUsers();
        [$developers, $sysadmins] = $this->createTeams($adminUser, $standardUser);
        $this->createNotes($adminUser, $standardUser, $developers, $sysadmins);
        $this->createFillerNotes($adminUser, $standardUser, $developers, $sysadmins);
        $this->createActivities($adminUser, $standardUser);
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

    private function createTeams(User $adminUser, User $standardUser): array
    {
        $developers = Team::create(['name' => 'developers']);
        $sysadmins = Team::create(['name' => 'sysadmins']);

        $developers->users()->attach($adminUser);
        $sysadmins->users()->attach($standardUser);

        return [$developers, $sysadmins];
    }

    private function createNotes(User $adminUser, User $standardUser, Team $developers, Team $sysadmins): void
    {
        $firstNote = Note::factory()->create([
            'user_id' => $adminUser->id,
            'title' => 'Livewire flyout modals lose focus on close',
            'body' => "If a `flux:modal variant=\"flyout\"` is closed via the escape key, focus does not always return to the trigger button.\n\nWorkaround is to set `wire:key` on the trigger. See the [Flux docs](https://fluxui.dev/components/modal) for the modal API.",
            'created_at' => now()->subDays(9),
            'updated_at' => now()->subDays(8)->addHour(),
            'read_count' => 2,
            'last_read_at' => now()->subDay(),
        ]);
        $firstNote->teams()->attach($developers);

        $followUpNote = Note::factory()->create([
            'user_id' => $standardUser->id,
            'title' => 'More flyout modal focus history',
            'body' => "Related to the escape-key problem - see note #{$firstNote->code} for the original write-up.\n\nTabbing out of the last field also skips the close button on some browsers.",
            'created_at' => now()->subDays(8),
            'updated_at' => now()->subDays(8),
            'read_count' => 1,
            'last_read_at' => now()->subDays(7),
        ]);
        $followUpNote->teams()->attach($developers);

        $developerDockerNote = Note::factory()->create([
            'user_id' => $adminUser->id,
            'title' => 'Docker layer cache misses on multi-stage builds',
            'body' => "COPY-ing the whole repo before `composer install` busts the build cache on every commit.\n\nCopy the dependency manifests first, install, then copy the rest - the install layer only rebuilds when the manifests change.",
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(4)->addHour(),
            'read_count' => 2,
            'last_read_at' => now()->subDays(2),
        ]);
        $developerDockerNote->teams()->attach($developers);

        $sysadminDockerNote = Note::factory()->create([
            'user_id' => $standardUser->id,
            'title' => 'Docker daemon log rotation filling /var on VM hosts',
            'body' => "The docker daemon's default json-file log driver never rotates, so chatty containers slowly fill /var.\n\nSet `log-opts` with `max-size` and `max-file` in /etc/docker/daemon.json and restart the daemon.",
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDay()->addHours(2),
            'read_count' => 1,
            'last_read_at' => now()->subDays(2),
        ]);
        $sysadminDockerNote->teams()->attach($sysadmins);

        Note::factory(3)->create(['user_id' => $standardUser->id]);
    }

    /**
     * A thousand filler notes so local browsing, search and pagination feel
     * realistic. Runs before createActivities so every filler note gets a
     * Created entry.
     */
    private function createFillerNotes(User $adminUser, User $standardUser, Team $developers, Team $sysadmins): void
    {
        Note::factory(1000)
            ->state(function () use ($adminUser, $standardUser) {
                $createdAt = fake()->dateTimeBetween('-2 years');

                return [
                    'user_id' => fake()->randomElement([$adminUser->id, $standardUser->id]),
                    'created_at' => $createdAt,
                    'updated_at' => fake()->dateTimeBetween($createdAt),
                    'read_count' => fake()->numberBetween(0, 40),
                ];
            })
            ->create()
            ->each(function (Note $note) use ($developers, $sysadmins) {
                $team = fake()->randomElement([$developers, $sysadmins, null]);

                $note->teams()->sync($team ? [$team->id] : []);
            });
    }

    /**
     * Seeding runs with nobody signed in, so nothing logs itself - create a
     * plausible week of history by hand, consistent with the notes' own
     * timestamps and read counts, so the admin activity page demos well.
     */
    private function createActivities(User $adminUser, User $standardUser): void
    {
        Note::all()->each(fn (Note $note) => Activity::factory()->create([
            'user_id' => $note->user_id,
            'note_id' => $note->id,
            'action' => ActivityAction::Created,
            'created_at' => $note->created_at,
        ]));

        $flyoutNote = Note::where('title', 'Livewire flyout modals lose focus on close')->sole();
        $followUpNote = Note::where('title', 'More flyout modal focus history')->sole();
        $dockerNote = Note::where('title', 'Docker layer cache misses on multi-stage builds')->sole();
        $daemonNote = Note::where('title', 'Docker daemon log rotation filling /var on VM hosts')->sole();

        collect([
            [$standardUser, $flyoutNote, ActivityAction::Read, now()->subDays(8)],
            [$adminUser, $flyoutNote, ActivityAction::Edited, now()->subDays(8)->addHour()],
            [$adminUser, $followUpNote, ActivityAction::Read, now()->subDays(7)],
            [$standardUser, $dockerNote, ActivityAction::Read, now()->subDays(4)],
            [$standardUser, $dockerNote, ActivityAction::Edited, now()->subDays(4)->addHour()],
            [$adminUser, $dockerNote, ActivityAction::Read, now()->subDays(2)],
            [$adminUser, $daemonNote, ActivityAction::Read, now()->subDays(2)],
            [$adminUser, $daemonNote, ActivityAction::Deleted, now()->subDay()],
            [$standardUser, $daemonNote, ActivityAction::Restored, now()->subDay()->addHours(2)],
            [$standardUser, $flyoutNote, ActivityAction::Read, now()->subDay()],
        ])->each(fn (array $entry) => Activity::factory()->create([
            'user_id' => $entry[0]->id,
            'note_id' => $entry[1]->id,
            'action' => $entry[2],
            'created_at' => $entry[3],
        ]));
    }
}

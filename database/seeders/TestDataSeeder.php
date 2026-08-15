<?php

namespace Database\Seeders;

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
        ]);
        $firstNote->teams()->attach($developers);

        $followUpNote = Note::factory()->create([
            'user_id' => $standardUser->id,
            'title' => 'More flyout modal focus history',
            'body' => "Related to the escape-key problem - see note #{$firstNote->code} for the original write-up.\n\nTabbing out of the last field also skips the close button on some browsers.",
        ]);
        $followUpNote->teams()->attach($developers);

        $developerDockerNote = Note::factory()->create([
            'user_id' => $adminUser->id,
            'title' => 'Docker layer cache misses on multi-stage builds',
            'body' => "COPY-ing the whole repo before `composer install` busts the build cache on every commit.\n\nCopy the dependency manifests first, install, then copy the rest - the install layer only rebuilds when the manifests change.",
        ]);
        $developerDockerNote->teams()->attach($developers);

        $sysadminDockerNote = Note::factory()->create([
            'user_id' => $standardUser->id,
            'title' => 'Docker daemon log rotation filling /var on VM hosts',
            'body' => "The docker daemon's default json-file log driver never rotates, so chatty containers slowly fill /var.\n\nSet `log-opts` with `max-size` and `max-file` in /etc/docker/daemon.json and restart the daemon.",
        ]);
        $sysadminDockerNote->teams()->attach($sysadmins);

        Note::factory(3)->create(['user_id' => $standardUser->id]);
    }
}

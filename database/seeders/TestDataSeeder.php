<?php

namespace Database\Seeders;

use App\Enums\ActivityAction;
use App\Models\Activity;
use App\Models\Note;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        [$adminUser, $standardUser] = $this->createUsers();
        $teams = $this->createTeams($adminUser, $standardUser);
        $this->createNotes($adminUser, $standardUser, $teams);
        $this->createFillerNotes($adminUser, $standardUser, $teams);
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

    /**
     * The directorate's real team structure, so team badges, scoping and
     * the MCP tools demo with plausible names. Each seeded user subscribes
     * to half the teams, leaving the other half out of scope so broader
     * search has something to find.
     *
     * @return Collection<string, Team>
     */
    private function createTeams(User $adminUser, User $standardUser): Collection
    {
        $teams = collect([
            'Service Resilience',
            'College Infrastructure',
            'Service Delivery',
            'Research Computing',
            'Applications & Data',
            'End-user Computing',
        ])->mapWithKeys(fn (string $name) => [$name => Team::create(['name' => $name])]);

        $teams->only(['Applications & Data', 'Research Computing', 'End-user Computing'])
            ->each(fn (Team $team) => $team->users()->attach($adminUser));

        $teams->only(['Service Resilience', 'College Infrastructure', 'Service Delivery'])
            ->each(fn (Team $team) => $team->users()->attach($standardUser));

        return $teams;
    }

    /**
     * @param  Collection<string, Team>  $teams
     */
    private function createNotes(User $adminUser, User $standardUser, Collection $teams): void
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
        $firstNote->teams()->attach($teams['Applications & Data']);

        $followUpNote = Note::factory()->create([
            'user_id' => $standardUser->id,
            'title' => 'More flyout modal focus history',
            'body' => "Related to the escape-key problem - see note #{$firstNote->code} for the original write-up.\n\nTabbing out of the last field also skips the close button on some browsers.",
            'created_at' => now()->subDays(8),
            'updated_at' => now()->subDays(8),
            'read_count' => 1,
            'last_read_at' => now()->subDays(7),
        ]);
        $followUpNote->teams()->attach($teams['Applications & Data']);

        $developerDockerNote = Note::factory()->create([
            'user_id' => $adminUser->id,
            'title' => 'Docker layer cache misses on multi-stage builds',
            'body' => "COPY-ing the whole repo before `composer install` busts the build cache on every commit.\n\nCopy the dependency manifests first, install, then copy the rest - the install layer only rebuilds when the manifests change.",
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(4)->addHour(),
            'read_count' => 2,
            'last_read_at' => now()->subDays(2),
        ]);
        $developerDockerNote->teams()->attach($teams['Applications & Data']);

        $sysadminDockerNote = Note::factory()->create([
            'user_id' => $standardUser->id,
            'title' => 'Docker daemon log rotation filling /var on VM hosts',
            'body' => "The docker daemon's default json-file log driver never rotates, so chatty containers slowly fill /var.\n\nSet `log-opts` with `max-size` and `max-file` in /etc/docker/daemon.json and restart the daemon.",
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDay()->addHours(2),
            'read_count' => 1,
            'last_read_at' => now()->subDays(2),
        ]);
        $sysadminDockerNote->teams()->attach($teams['Service Resilience']);

        Note::factory(3)->create(['user_id' => $standardUser->id]);

        collect($this->realisticNotes())->each(function (array $note) use ($teams, $adminUser, $standardUser) {
            $createdAt = now()->subDays($note['days_ago']);

            Note::factory()->create([
                'user_id' => fake()->randomElement([$adminUser->id, $standardUser->id]),
                'title' => $note['title'],
                'body' => $note['body'],
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
                'read_count' => fake()->numberBetween(0, 25),
            ])->teams()->attach($teams[$note['team']]);
        });
    }

    /**
     * Hand-written notes in the pot's real voice - symptom, cause, fix -
     * flavoured for our estate (Windows fleet, Rocky/Alma/Debian, SLURM
     * clusters, research software licences, Laravel apps, Mac end-users),
     * so browsing and search demo with believable content.
     *
     * @return list<array{title: string, body: string, team: string, days_ago: int}>
     */
    private function realisticNotes(): array
    {
        return [
            [
                'title' => 'Zabbix floods nodata alerts the night the clocks change',
                'body' => "Every October there is a 1am burst of nodata alerts for hosts that are actually fine.\n\nThe agents report on schedule, but the trigger windows are shorter than the repeated hour when BST ends. Widen the nodata window to at least 90 minutes, or the first Sunday-night callout of winter is always a false one.",
                'team' => 'Service Resilience',
                'days_ago' => 300,
            ],
            [
                'title' => 'Postfix defers all relay mail after a smarthost cert renewal',
                'body' => "The queue backed up with 'Server certificate not verified' on every deferred message.\n\nThe smarthost's new certificate came from a different CA and our relay pinned the old chain in `smtp_tls_CAfile`. Point it at the distro CA bundle instead of a hand-copied chain and the queue drains itself.",
                'team' => 'Service Resilience',
                'days_ago' => 45,
            ],
            [
                'title' => 'Restart=always hides a crash-looping service from monitoring',
                'body' => "A unit with `Restart=always` can crash every few seconds for days while every check shows it active (running).\n\nAdd `StartLimitIntervalSec` and `StartLimitBurst` so systemd gives up after a burst of restarts, and alert on the unit's `NRestarts` property rather than just its state.",
                'team' => 'Service Resilience',
                'days_ago' => 120,
            ],
            [
                'title' => 'Rocky 9 kickstart hangs at network start on tagged VLANs',
                'body' => "PXE builds stall waiting for the network on hosts whose port carries a tagged VLAN.\n\nThe installer needs the VLAN spelled out in the boot arguments (`ip=...` plus `vlan=`), or it sits at 'Starting installer' forever. Untagged ports build fine, which is why only some racks showed it.",
                'team' => 'College Infrastructure',
                'days_ago' => 200,
            ],
            [
                'title' => 'Windows VMs drift off domain time after vMotion',
                'body' => "Kerberos logins start failing with clock skew errors on a handful of VMs.\n\nVMware host time sync and w32time fight after a vMotion to a host with a poor clock. Domain-joined guests should sync from the domain hierarchy only - turn off periodic host sync in VMware Tools.",
                'team' => 'College Infrastructure',
                'days_ago' => 90,
            ],
            [
                'title' => 'eduroam onboarding profiles pin the old RADIUS CA',
                'body' => "After renewing the RADIUS server certificate, devices set up through the onboarding portal refuse to connect while manually configured ones still work.\n\nThe onboarding profile pins the issuing CA, not the server certificate. Renew from the same CA, or every enrolled device needs a fresh profile.",
                'team' => 'College Infrastructure',
                'days_ago' => 160,
            ],
            [
                'title' => "New starters can't open shared mailboxes on day one",
                'body' => "Permissions look right in the admin centre but Outlook keeps prompting for credentials.\n\nThe mailbox permission was granted against an account the directory sync had not finished linking. Run a delta sync after the provisioning job completes, not before, and the day-one tickets stop.",
                'team' => 'Service Delivery',
                'days_ago' => 75,
            ],
            [
                'title' => 'Replacing a phone breaks MFA before the user tells anyone',
                'body' => "The 'lost phone' call usually arrives after three failed sign-ins and a locked account.\n\nClear the old authenticator method and issue a Temporary Access Pass in the same call. Re-registering while the old method still exists just re-pairs the dead phone.",
                'team' => 'Service Delivery',
                'days_ago' => 30,
            ],
            [
                'title' => 'Mac users lose printers after a queue driver update',
                'body' => "Updating the driver on the print server quietly breaks the Macs that already had the queue installed - jobs vanish with no error.\n\nCUPS keeps the old PPD. The queue has to be removed and re-added; the self-service reinstall does it in one click, so point users there rather than talking them through Printers and Scanners.",
                'team' => 'Service Delivery',
                'days_ago' => 15,
            ],
            [
                'title' => "SLURM jobs pend on 'Resources' while GPU nodes sit idle",
                'body' => "`squeue` shows Resources but `scontrol show node` lists free GPUs.\n\nThe default memory request was claiming the whole node, so the scheduler could not co-locate jobs. GPU jobs need an explicit `--mem` alongside `--gres=gpu:1`; we now set `DefMemPerGPU` in slurm.conf so the default is sane.",
                'team' => 'Research Computing',
                'days_ago' => 60,
            ],
            [
                'title' => 'MATLAB licence checkout fails from compute nodes only',
                'body' => "Checkouts work on the login node; batch jobs die with 'cannot connect to license server'.\n\nFlexNet uses two ports: 27000 for lmgrd plus a floating one for the MLM vendor daemon, and the compute VLAN only allowed the first. Pin the vendor daemon port in the licence file and open both.",
                'team' => 'Research Computing',
                'days_ago' => 130,
            ],
            [
                'title' => 'STAR-CCM+ Power-on-Demand needs outbound web from the nodes',
                'body' => "PoD keys validate against the vendor's licence servers at job start, and the compute nodes have no direct internet access.\n\nExport the https proxy variables in the job template - the solver honours them. Without that, runs fail with a licence error that reads like a bad key.",
                'team' => 'Research Computing',
                'days_ago' => 110,
            ],
            [
                'title' => 'Alma 9 kernel updates silently break InfiniBand',
                'body' => "Nodes come back from patching with IB down and MPI falling back to Ethernet - jobs run about 10x slower rather than failing, so it can go unnoticed for days.\n\nThe OFED kmod has to be rebuilt for the new kernel; DKMS handles it when the kernel headers are installed. The patching playbook now checks `ibstat` after every reboot.",
                'team' => 'Research Computing',
                'days_ago' => 25,
            ],
            [
                'title' => 'Queue workers keep running old code after a deploy',
                'body' => "A fixed bug kept happening in production because the Horizon workers were still on last week's release.\n\nWorkers hold the old code in memory until restarted. `php artisan queue:restart` belongs in the deploy script, after the new release goes live.",
                'team' => 'Applications & Data',
                'days_ago' => 50,
            ],
            [
                'title' => 'Redis eviction quietly eats queued jobs under memory pressure',
                'body' => "Jobs vanished with nothing in the failed jobs table during a busy spell.\n\nThe cache and the queues shared one Redis with `maxmemory-policy allkeys-lru`, so under pressure it evicted queue keys. Queues get their own instance with `noeviction`; the cache can keep evicting.",
                'team' => 'Applications & Data',
                'days_ago' => 95,
            ],
            [
                'title' => "Legacy MySQL schemas reject emoji with 'Incorrect string value'",
                'body' => "Apps still on utf8 (the three-byte one) fall over the first time someone pastes an emoji into a form.\n\nConvert the tables to utf8mb4, and check the framework's connection charset too - otherwise the app keeps writing in the old encoding.",
                'team' => 'Applications & Data',
                'days_ago' => 240,
            ],
            [
                'title' => 'Teams cache bloats roaming profiles past the copy timeout',
                'body' => "Logins crawl and some profiles stop syncing entirely.\n\nThe Teams cache under AppData grows unbounded and roams by default. Exclude it from the roaming profile - it rebuilds harmlessly on first launch.",
                'team' => 'End-user Computing',
                'days_ago' => 180,
            ],
            [
                'title' => 'Macs prompt for the old keychain password after an AD reset',
                'body' => "Every AD password change earns the user a login keychain prompt loop on their Mac.\n\nThe local keychain still holds the old password. 'Update keychain password' fixes it when they remember the old one; otherwise reset the login keychain and warn them saved passwords are lost either way.",
                'team' => 'End-user Computing',
                'days_ago' => 20,
            ],
            [
                'title' => 'Homebrew fails behind the proxy on fresh Macs',
                'body' => "`brew install` dies with SSL errors on new machines on the wired network.\n\nThe proxy does TLS inspection and brew's bundled curl does not trust our root CA. Push the CA via the management profile and set `HTTPS_PROXY` in the default shell profile.",
                'team' => 'End-user Computing',
                'days_ago' => 140,
            ],
            [
                'title' => 'Defender exclusions arrive later than the software that needs them',
                'body' => "Newly built machines run the engineering installers before the policy refresh delivers the AV exclusions, so first installs of the big CAD packages crawl or time out.\n\nForce a policy sync at the end of the build task sequence, before the software phase starts.",
                'team' => 'End-user Computing',
                'days_ago' => 70,
            ],
        ];
    }

    /**
     * A thousand filler notes so local browsing, search and pagination feel
     * realistic. Runs before createActivities so every filler note gets a
     * Created entry.
     */
    /**
     * @param  Collection<string, Team>  $teams
     */
    private function createFillerNotes(User $adminUser, User $standardUser, Collection $teams): void
    {
        $teamChoices = $teams->values()->push(null)->all();

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
            ->each(function (Note $note) use ($teamChoices) {
                $team = fake()->randomElement($teamChoices);

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

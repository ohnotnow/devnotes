<?php

namespace App\Jobs;

use App\Models\Note;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ImportNotes implements ShouldQueue
{
    use Queueable;

    /**
     * An admin-triggered import fails loudly once - no auto-retry, and the
     * cleanup in handle() must not delete the file from under a retry.
     */
    public int $tries = 1;

    /** @var array{imported: int, skipped: array<string>, overwritten: array<string>, recoded: array<array{old: string, new: string}>, users_created: int, teams_created: int} */
    private array $report = ['imported' => 0, 'skipped' => [], 'overwritten' => [], 'recoded' => [], 'users_created' => 0, 'teams_created' => 0];

    /**
     * @param  array<int, string>  $overwriteUlids
     */
    public function __construct(
        public string $disk,
        public string $path,
        public bool $createUsers = true,
        public ?User $fallbackOwner = null,
        public array $overwriteUlids = [],
    ) {}

    /**
     * Callers that want the report must run the job in-process:
     * (new ImportNotes(...))->handle(). dispatchSync discards the
     * return value of a ShouldQueue job; queued dispatch discards it too.
     *
     * @return array{imported: int, skipped: array<string>, recoded: array<array{old: string, new: string}>, users_created: int, teams_created: int}
     */
    public function handle(): array
    {
        try {
            foreach ($this->exportedNotes() as $exportedNote) {
                $this->import($exportedNote);
            }

            return $this->report;
        } finally {
            Storage::disk($this->disk)->delete($this->path);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function exportedNotes(): array
    {
        $payload = json_decode(Storage::disk($this->disk)->get($this->path), true);

        if (($payload['version'] ?? null) !== 1) {
            throw new RuntimeException('Unsupported export version');
        }

        return $payload['notes'];
    }

    /**
     * @param  array<string, mixed>  $exportedNote
     */
    private function import(array $exportedNote): void
    {
        $existingNote = Note::withTrashed()->where('ulid', $exportedNote['ulid'])->first();

        if ($existingNote && in_array($exportedNote['ulid'], $this->overwriteUlids)) {
            $this->overwrite($existingNote, $exportedNote);
            $this->report['overwritten'][] = $existingNote->code;

            return;
        }

        if ($existingNote) {
            $this->report['skipped'][] = $exportedNote['code'];

            return;
        }

        $owner = $this->resolveOwner($exportedNote['author']);
        $note = $this->createNote($exportedNote, $owner);
        $note->teams()->attach($this->resolveTeamIds($exportedNote['teams']));
        $this->report['imported']++;
    }

    /**
     * The file's version wins wholesale - except ulid and code, which are
     * identity: same ulid means same origin means same code.
     *
     * @param  array<string, mixed>  $exportedNote
     */
    private function overwrite(Note $note, array $exportedNote): void
    {
        $owner = $this->resolveOwner($exportedNote['author']);

        Note::withoutSyncingToSearch(function () use ($note, $exportedNote, $owner): void {
            $note->timestamps = false;
            $note->forceFill([
                'title' => $exportedNote['title'],
                'body' => $exportedNote['body'],
                'user_id' => $owner->id,
                'created_at' => $exportedNote['created_at'],
                'updated_at' => $exportedNote['updated_at'],
                'deleted_at' => $exportedNote['deleted_at'],
            ])->save();

            $note->teams()->sync($this->resolveTeamIds($exportedNote['teams']));
        });

        $note->trashed() ? $note->unsearchable() : $note->searchable();
    }

    /**
     * An author email matching an existing user always wins; createUsers only
     * decides what happens to unknown emails.
     *
     * @param  array<string, mixed>  $author
     */
    private function resolveOwner(array $author): User
    {
        $existingUser = User::where('email', $author['email'])->first();

        if ($existingUser) {
            return $existingUser;
        }

        if (! $this->createUsers) {
            return $this->fallbackOwner;
        }

        $this->report['users_created']++;

        return User::create([
            'username' => '',
            'email' => $author['email'],
            'forenames' => $author['forenames'],
            'surname' => $author['surname'],
            'is_admin' => $author['is_admin'],
            'is_staff' => $author['is_staff'],
            'password' => bcrypt(Str::random(64)),
        ]);
    }

    /**
     * @param  array<string, mixed>  $exportedNote
     */
    private function createNote(array $exportedNote, User $owner): Note
    {
        // A stranger already using this code gets priority; leaving ours blank
        // lets the creating hook mint a fresh one, reported in `recoded`.
        $codeIsTaken = Note::withTrashed()->where('code', $exportedNote['code'])->exists();

        $note = Note::withoutSyncingToSearch(function () use ($exportedNote, $owner, $codeIsTaken): Note {
            $note = new Note;
            $note->timestamps = false;
            $note->forceFill([
                'ulid' => $exportedNote['ulid'],
                'code' => $codeIsTaken ? null : $exportedNote['code'],
                'title' => $exportedNote['title'],
                'body' => $exportedNote['body'],
                'user_id' => $owner->id,
                'created_at' => $exportedNote['created_at'],
                'updated_at' => $exportedNote['updated_at'],
                'deleted_at' => $exportedNote['deleted_at'],
            ])->save();

            return $note;
        });

        if ($codeIsTaken) {
            $this->report['recoded'][] = ['old' => $exportedNote['code'], 'new' => $note->code];
        }

        if (! $note->trashed()) {
            $note->searchable();
        }

        return $note;
    }

    /**
     * @param  array<int, string>  $teamNames
     * @return array<int, int>
     */
    private function resolveTeamIds(array $teamNames): array
    {
        return collect($teamNames)->map(function (string $teamName): int {
            $team = Team::firstOrCreate(['name' => $teamName]);

            if ($team->wasRecentlyCreated) {
                $this->report['teams_created']++;
            }

            return $team->id;
        })->all();
    }
}

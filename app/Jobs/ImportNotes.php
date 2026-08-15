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

    /** @var array{imported: int, skipped: array<int>, users_created: int, teams_created: int} */
    private array $report = ['imported' => 0, 'skipped' => [], 'users_created' => 0, 'teams_created' => 0];

    public function __construct(
        public string $disk,
        public string $path,
        public bool $createUsers = true,
        public ?User $fallbackOwner = null,
    ) {}

    /**
     * Callers that want the report must run the job in-process:
     * (new ImportNotes(...))->handle(). dispatchSync discards the
     * return value of a ShouldQueue job; queued dispatch discards it too.
     *
     * @return array{imported: int, skipped: array<int>, users_created: int, teams_created: int}
     */
    public function handle(): array
    {
        try {
            foreach ($this->exportedNotes() as $exportedNote) {
                $this->import($exportedNote);
            }

            $this->resetPostgresNoteSequence();

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
        if (Note::withTrashed()->find($exportedNote['id'])) {
            $this->report['skipped'][] = $exportedNote['id'];

            return;
        }

        $owner = $this->resolveOwner($exportedNote['author']);
        $note = $this->createNote($exportedNote, $owner);
        $this->attachTeams($note, $exportedNote['teams']);
        $this->report['imported']++;
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
        $note = Note::withoutSyncingToSearch(function () use ($exportedNote, $owner): Note {
            $note = new Note;
            $note->timestamps = false;
            $note->forceFill([
                'id' => $exportedNote['id'],
                'title' => $exportedNote['title'],
                'body' => $exportedNote['body'],
                'user_id' => $owner->id,
                'created_at' => $exportedNote['created_at'],
                'updated_at' => $exportedNote['updated_at'],
                'deleted_at' => $exportedNote['deleted_at'],
            ])->save();

            return $note;
        });

        if (! $note->trashed()) {
            $note->searchable();
        }

        return $note;
    }

    /**
     * @param  array<int, string>  $teamNames
     */
    private function attachTeams(Note $note, array $teamNames): void
    {
        foreach ($teamNames as $teamName) {
            $team = Team::firstOrCreate(['name' => $teamName]);

            if ($team->wasRecentlyCreated) {
                $this->report['teams_created']++;
            }

            $note->teams()->attach($team);
        }
    }

    /**
     * Postgres does not advance a sequence past explicit-id inserts (MySQL and
     * SQLite do), so without this the next created note would collide with an
     * imported id. Raw statement agreed as a one-off convention exception.
     */
    private function resetPostgresNoteSequence(): void
    {
        if (Note::query()->getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        $maxId = (int) Note::withTrashed()->max('id');

        if ($maxId === 0) {
            return;
        }

        Note::query()->getConnection()->statement(
            "select setval(pg_get_serial_sequence('notes', 'id'), ?)",
            [$maxId]
        );
    }
}

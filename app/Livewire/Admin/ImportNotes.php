<?php

namespace App\Livewire\Admin;

use App\Jobs\ImportNotes as ImportNotesJob;
use App\Models\Note;
use App\Models\Team;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class ImportNotes extends Component
{
    use WithFileUploads;

    public $file;

    public bool $createUsers = true;

    /** @var array<string, string> ulid => skip|overwrite */
    public array $decisions = [];

    public array $preview = [];

    public function render()
    {
        return view('livewire.admin.import-notes');
    }

    public function updatedFile(): void
    {
        $this->reset('preview', 'decisions');
        $this->resetValidation();
        $this->validate(['file' => ['required', 'file']]);

        $payload = json_decode($this->file->get(), true);

        if (! ImportNotesJob::describesNotes($payload)) {
            $this->reset('file');
            $this->addError('file', 'That does not look like a devnotes export file.');

            return;
        }

        $this->preview = $this->buildPreview($payload['notes']);
        $this->decisions = collect($this->preview['matches'])
            ->mapWithKeys(fn (array $match): array => [$match['ulid'] => 'skip'])
            ->all();
    }

    public function removeFile(): void
    {
        $this->file?->delete();
        $this->reset('file', 'preview', 'decisions');
    }

    public function import(): void
    {
        $disk = config('filesystems.default');
        $path = 'imports/'.Str::uuid().'.json';
        Storage::disk($disk)->put($path, $this->file->get());

        ImportNotesJob::dispatch(
            $disk,
            $path,
            $this->createUsers,
            $this->createUsers ? null : Auth::user(),
            array_keys($this->decisions, 'overwrite'),
        );

        Flux::toast('Import queued - notes will appear shortly.', variant: 'success');
        $this->reset('file', 'preview', 'decisions', 'createUsers');
    }

    /**
     * A snapshot, deliberately: the job re-derives everything when it runs,
     * and this page just tells the admin what that will mean. Identical
     * matches are counted, never listed - a decision with only one sane
     * answer trains people to stop reading. Only drift earns a question.
     *
     * @param  array<int, array<string, mixed>>  $exportedNotes
     * @return array<string, mixed>
     */
    private function buildPreview(array $exportedNotes): array
    {
        $exported = collect($exportedNotes);
        $existingByUlid = Note::withTrashed()->with(['user', 'teams'])
            ->whereIn('ulid', $exported->pluck('ulid'))->get()->keyBy('ulid');
        $takenCodes = Note::withTrashed()->whereIn('code', $exported->pluck('code'))->pluck('code');

        [$matched, $new] = $exported->partition(fn (array $note): bool => $existingByUlid->has($note['ulid']));
        [$drifted, $identical] = $matched->partition(
            fn (array $note): bool => $this->differences($existingByUlid[$note['ulid']], $note) !== []
        );

        return [
            'new_count' => $new->count(),
            'identical_count' => $identical->count(),
            'users_to_create' => $exported->pluck('author.email')->unique()
                ->diff(User::whereIn('email', $exported->pluck('author.email'))->pluck('email'))->count(),
            'teams_to_create' => $exported->pluck('teams')->flatten()->unique()
                ->diff(Team::pluck('name'))->count(),
            'matches' => $drifted->map(fn (array $note): array => [
                'ulid' => $note['ulid'],
                'code' => $existingByUlid[$note['ulid']]->code,
                'incoming_title' => $note['title'],
                'existing_title' => $existingByUlid[$note['ulid']]->title,
                'differs' => $this->differences($existingByUlid[$note['ulid']], $note),
            ])->values()->all(),
            'collisions' => $new->filter(fn (array $note): bool => $takenCodes->contains($note['code']))
                ->map(fn (array $note): array => ['code' => $note['code'], 'title' => $note['title']])
                ->values()->all(),
        ];
    }

    /**
     * Compares exactly the fields an overwrite would write.
     *
     * @param  array<string, mixed>  $exportedNote
     * @return array<int, string>
     */
    private function differences(Note $existingNote, array $exportedNote): array
    {
        $differs = [];

        if ($existingNote->title !== $exportedNote['title']) {
            $differs[] = 'title';
        }

        if ($existingNote->body !== $exportedNote['body']) {
            $differs[] = 'body';
        }

        if ($existingNote->teams->pluck('name')->sort()->values()->all() !== collect($exportedNote['teams'])->sort()->values()->all()) {
            $differs[] = 'teams';
        }

        if ($existingNote->user->email !== $exportedNote['author']['email']) {
            $differs[] = 'author';
        }

        if ($existingNote->created_at?->toJSON() !== $exportedNote['created_at']
            || $existingNote->updated_at?->toJSON() !== $exportedNote['updated_at']) {
            $differs[] = 'timestamps';
        }

        if ($existingNote->deleted_at?->toJSON() !== $exportedNote['deleted_at']) {
            $differs[] = 'deleted state';
        }

        return $differs;
    }
}

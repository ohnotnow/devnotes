<?php

namespace App\Livewire;

use App\Models\Note;
use App\Models\Team;
use Flux\Flux;
use Illuminate\Support\Arr;
use Livewire\Attributes\On;
use Livewire\Component;

class NoteForm extends Component
{
    public array $editing = [
        'id' => null,
        'title' => '',
        'body' => '',
    ];

    public array $selectedTeamIds = [];

    protected array $validationAttributes = [
        'editing.title' => 'title',
        'editing.body' => 'note',
    ];

    public function render()
    {
        return view('livewire.note-form', [
            'teams' => Team::orderBy('name')->get(),
        ]);
    }

    #[On('note-editor:create')]
    public function openCreate(): void
    {
        $this->reset('editing');
        $this->selectedTeamIds = auth()->user()->defaultNoteTeams()->pluck('teams.id')->all();
        $this->resetValidation();

        Flux::modal('note-editor')->show();
    }

    #[On('note-editor:edit')]
    public function openEdit(int $id): void
    {
        $note = Note::findOrFail($id);
        $this->editing = $note->only(['id', 'title', 'body']);
        $this->selectedTeamIds = $note->teams()->pluck('teams.id')->all();
        $this->resetValidation();

        Flux::modal('note-editor')->show();
    }

    public function save(): void
    {
        $this->validate([
            'editing.title' => ['required', 'string', 'max:255'],
            'editing.body' => ['required', 'string'],
            'selectedTeamIds' => ['array'],
            'selectedTeamIds.*' => ['exists:teams,id'],
        ]);

        $note = Note::findOrNew($this->editing['id']);
        $note->fill(Arr::only($this->editing, ['title', 'body']));
        if (! $note->exists) {
            $note->user_id = auth()->id();
        }
        $note->save();
        $note->teams()->sync($this->selectedTeamIds);

        Flux::modal('note-editor')->close();
        Flux::toast("Saved note #{$note->code}", variant: 'success');
        $this->dispatch('note-saved');
    }
}

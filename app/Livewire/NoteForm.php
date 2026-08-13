<?php

namespace App\Livewire;

use App\Models\Note;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Component;

class NoteForm extends Component
{
    public array $editing = [
        'id' => null,
        'title' => '',
        'body' => '',
    ];

    protected array $validationAttributes = [
        'editing.title' => 'title',
        'editing.body' => 'note',
    ];

    #[On('note-editor:create')]
    public function openCreate(): void
    {
        $this->reset('editing');
        $this->resetValidation();

        Flux::modal('note-editor')->show();
    }

    #[On('note-editor:edit')]
    public function openEdit(int $id): void
    {
        $this->editing = Note::findOrFail($id)->only(['id', 'title', 'body']);
        $this->resetValidation();

        Flux::modal('note-editor')->show();
    }

    public function save(): void
    {
        $this->validate([
            'editing.title' => ['required', 'string', 'max:255'],
            'editing.body' => ['required', 'string'],
        ]);

        $note = Note::findOrNew($this->editing['id']);
        $note->fill($this->editing);
        if (! $note->exists) {
            $note->user_id = auth()->id();
        }
        $note->save();

        Flux::modal('note-editor')->close();
        Flux::toast("Saved note #{$note->id}", variant: 'success');
        $this->dispatch('note-saved');
    }

    public function render()
    {
        return view('livewire.note-form');
    }
}

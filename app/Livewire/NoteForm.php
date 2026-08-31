<?php

namespace App\Livewire;

use App\Models\Channel;
use App\Models\Note;
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

    public array $selectedChannelIds = [];

    protected array $validationAttributes = [
        'editing.title' => 'title',
        'editing.body' => 'note',
    ];

    public function render()
    {
        return view('livewire.note-form', [
            'channels' => Channel::orderBy('name')->get(),
        ]);
    }

    #[On('note-editor:create')]
    public function openCreate(): void
    {
        $this->reset('editing');
        $this->selectedChannelIds = auth()->user()->defaultNoteChannels()->pluck('channels.id')->all();
        $this->resetValidation();

        Flux::modal('note-editor')->show();
    }

    #[On('note-editor:edit')]
    public function openEdit(int $id): void
    {
        $note = Note::findOrFail($id);
        $this->editing = $note->only(['id', 'title', 'body']);
        $this->selectedChannelIds = $note->channels()->pluck('channels.id')->all();
        $this->resetValidation();

        Flux::modal('note-editor')->show();
    }

    public function save(): void
    {
        $this->validate([
            'editing.title' => ['required', 'string', 'max:255'],
            'editing.body' => ['required', 'string'],
            'selectedChannelIds' => ['array'],
            'selectedChannelIds.*' => ['exists:channels,id'],
        ]);

        $note = Note::findOrNew($this->editing['id']);
        $note->fill(Arr::only($this->editing, ['title', 'body']));
        if (! $note->exists) {
            $note->user_id = auth()->id();
        }
        $note->save();
        $note->channels()->sync($this->selectedChannelIds);

        Flux::modal('note-editor')->close();
        Flux::toast("Saved note #{$note->code}", variant: 'success');
        $this->dispatch('note-saved');
    }
}

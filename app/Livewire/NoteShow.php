<?php

namespace App\Livewire;

use App\Models\Note;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Component;

class NoteShow extends Component
{
    public Note $note;

    public function mount(): void
    {
        $this->note->incrementReadCount();
    }

    #[On('note-saved')]
    public function refreshNote(): void
    {
        $this->note->refresh();
    }

    public function delete(): void
    {
        $this->note->delete();

        Flux::toast("Deleted note #{$this->note->code}");
        $this->redirectRoute('home', navigate: true);
    }

    public function restore(): void
    {
        $this->note->restore();

        Flux::toast("Restored note #{$this->note->code}");
    }

    public function render()
    {
        return view('livewire.note-show');
    }
}

<?php

namespace App\Livewire;

use App\Models\Note;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Component;

class NoteShow extends Component
{
    public Note $note;

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

    public function render()
    {
        return view('livewire.note-show');
    }
}

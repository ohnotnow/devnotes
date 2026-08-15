<?php

namespace App\Livewire;

use App\Models\Note;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class NotesIndex extends Component
{
    use WithPagination;

    #[Url]
    public $search = '';

    #[Url]
    public $broader = false;

    #[On('note-saved')]
    public function noteSaved(): void
    {
        // Re-render so a newly saved note appears in the list.
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedBroader(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.notes-index', [
            'notes' => $this->search
                ? Note::searchScoped(auth()->user(), $this->search, filter_var($this->broader, FILTER_VALIDATE_BOOL))->paginate(20)
                : Note::with('user')->latest()->paginate(20),
        ]);
    }
}

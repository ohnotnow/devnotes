<?php

namespace App\Livewire;

use App\Models\Note;
use Flux\Flux;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class TidyNotes extends Component
{
    use WithPagination;

    #[Url]
    public $showAll = false;

    public ?int $previewNoteId = null;

    public function preview(int $noteId): void
    {
        $this->previewNoteId = $noteId;

        Flux::modal('note-preview')->show();
    }

    public function delete(int $noteId): void
    {
        $note = Note::findOrFail($noteId);
        $note->delete();

        Flux::toast("Deleted note #{$note->code}");
    }

    public function updatedShowAll(): void
    {
        $this->resetPage();
    }

    private function isShowingAll(): bool
    {
        return auth()->user()->can('admin') && filter_var($this->showAll, FILTER_VALIDATE_BOOL);
    }

    public function render()
    {
        $showingAll = $this->isShowingAll();

        return view('livewire.tidy-notes', [
            'showingAll' => $showingAll,
            'previewNote' => $this->previewNoteId ? Note::with(['user', 'teams'])->find($this->previewNoteId) : null,
            'notes' => Note::with('user')
                ->when(! $showingAll, fn ($query) => $query->where('user_id', auth()->id()))
                ->orderBy('read_count')
                ->orderBy('last_read_at')
                ->paginate(20),
        ]);
    }
}

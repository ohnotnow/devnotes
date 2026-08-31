<?php

namespace App\Livewire;

use App\Models\Channel;
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
    public $channels = [];

    public function mount(): void
    {
        if ($this->channels === []) {
            $this->channels = auth()->user()->subscribedChannels()->pluck('channels.id')->all();
        }
    }

    public function render()
    {
        $channelIds = array_map('intval', (array) $this->channels);

        $notes = Note::with(['user', 'channels'])
            ->inChannels($channelIds)
            ->when($this->search, fn ($query) => $query->searchWords($this->search))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('livewire.notes-index', [
            'notes' => $notes,
            'filtering' => $channelIds !== [],
            'channelOptions' => Channel::orderBy('name')->get(),
        ]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedChannels(): void
    {
        $this->resetPage();
    }

    public function filterChannel(int $channelId): void
    {
        $this->channels = [$channelId];
        $this->resetPage();
    }

    #[On('note-saved')]
    public function noteSaved(): void
    {
        // Re-render so a newly saved note appears in the list.
    }
}

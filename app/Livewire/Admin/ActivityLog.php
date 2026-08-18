<?php

namespace App\Livewire\Admin;

use App\Models\Activity;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ActivityLog extends Component
{
    use WithPagination;

    #[Url]
    public $search = '';

    #[Url]
    public $action = '';

    public function render()
    {
        return view('livewire.admin.activity-log', [
            'activities' => Activity::with('user')
                ->when($this->search !== '', fn ($query) => $query->matching($this->search))
                ->when($this->action !== '', fn ($query) => $query->where('action', $this->action))
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(20),
        ]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedAction(): void
    {
        $this->resetPage();
    }
}

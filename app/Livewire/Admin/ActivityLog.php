<?php

namespace App\Livewire\Admin;

use App\Enums\ActivityAction;
use App\Models\Activity;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ActivityLog extends Component
{
    use WithPagination;

    #[Url]
    public $search = '';

    #[Url]
    public $actions = [];

    public function render()
    {
        $activities = Activity::with('user')
            ->when($this->search !== '', fn ($query) => $query->matching($this->search))
            ->when($this->actions !== [], fn ($query) => $query->whereIn('action', $this->actions))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('livewire.admin.activity-log', [
            'activities' => $activities,
            'resultsSummary' => "Showing {$activities->total()} ".Str::plural('result', $activities->total()),
            'actionFilters' => collect(ActivityAction::cases())->map(fn (ActivityAction $action): array => [
                'value' => $action->value,
                'label' => $action->label(),
                'active' => in_array($action->value, $this->actions),
                'colour' => in_array($action->value, $this->actions) ? $action->colour() : 'zinc',
            ]),
        ]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function toggleAction(string $action): void
    {
        $this->actions = in_array($action, $this->actions)
            ? array_values(array_diff($this->actions, [$action]))
            : [...$this->actions, $action];

        $this->resetPage();
    }
}

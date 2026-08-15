<?php

namespace App\Livewire\Admin;

use App\Models\Team;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Teams extends Component
{
    public array $editing = [
        'id' => null,
        'name' => '',
    ];

    public ?Team $deletingTeam = null;

    public function openCreate(): void
    {
        $this->reset('editing');
        $this->resetValidation();

        Flux::modal('team-editor')->show();
    }

    public function openEdit(int $id): void
    {
        $this->editing = Team::findOrFail($id)->only(['id', 'name']);
        $this->resetValidation();

        Flux::modal('team-editor')->show();
    }

    public function save(): void
    {
        $this->validate([
            'editing.name' => ['required', 'string', 'max:255', Rule::unique('teams', 'name')->ignore($this->editing['id'])],
        ]);

        $team = Team::findOrNew($this->editing['id']);
        $team->name = $this->editing['name'];
        $team->save();

        Flux::modal('team-editor')->close();
        Flux::toast("Saved team {$team->name}", variant: 'success');
    }

    public function openDelete(int $id): void
    {
        $this->deletingTeam = Team::findOrFail($id);

        Flux::modal('team-delete')->show();
    }

    public function deleteTeam(): void
    {
        if ($this->deletingTeam === null) {
            return;
        }

        $name = $this->deletingTeam->name;
        $this->deletingTeam->delete();

        $this->reset('deletingTeam');
        Flux::modal('team-delete')->close();
        Flux::toast("Deleted team {$name} - its notes now show in every search");
    }

    public function render()
    {
        return view('livewire.admin.teams', [
            'teams' => Team::orderBy('name')->withCount(['users', 'notes'])->get(),
        ]);
    }
}

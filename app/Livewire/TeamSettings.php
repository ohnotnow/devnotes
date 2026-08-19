<?php

namespace App\Livewire;

use App\Models\Team;
use Flux\Flux;
use Livewire\Component;

class TeamSettings extends Component
{
    public array $subscribedTeamIds = [];

    public array $defaultTeamIds = [];

    public function mount(): void
    {
        $this->subscribedTeamIds = auth()->user()->subscribedTeams()->pluck('teams.id')->all();
        $this->defaultTeamIds = auth()->user()->defaultNoteTeams()->pluck('teams.id')->all();
    }

    public function render()
    {
        return view('livewire.team-settings', [
            'teams' => Team::orderBy('name')->get(),
        ]);
    }

    public function save(): void
    {
        $this->validate([
            'subscribedTeamIds' => ['array'],
            'subscribedTeamIds.*' => ['exists:teams,id'],
            'defaultTeamIds' => ['array'],
            'defaultTeamIds.*' => ['exists:teams,id'],
        ]);

        auth()->user()->syncTeamPreferences(
            array_map('intval', $this->subscribedTeamIds),
            array_map('intval', $this->defaultTeamIds),
        );

        Flux::toast('Teams saved', variant: 'success');
    }
}

<div class="max-w-lg space-y-8">
    <div>
        <flux:heading size="xl" level="1">Teams</flux:heading>
        <flux:text class="mt-2">Teams only tune search - nothing is ever hidden, and anyone can still read and edit every note.</flux:text>
    </div>

    <flux:checkbox.group wire:model="subscribedTeamIds" label="Teams I read" description="Search results come from these teams (plus notes with no team at all).">
        @foreach ($teams as $team)
            <flux:checkbox :value="$team->id" :label="$team->name" wire:key="read-team-{{ $team->id }}" />
        @endforeach
    </flux:checkbox.group>

    <flux:checkbox.group wire:model="defaultTeamIds" label="My new notes go to" description="Notes you capture default to these teams unless you pick others on the note.">
        @foreach ($teams as $team)
            <flux:checkbox :value="$team->id" :label="$team->name" wire:key="default-team-{{ $team->id }}" />
        @endforeach
    </flux:checkbox.group>

    <flux:button variant="primary" wire:click="save">Save teams</flux:button>
</div>

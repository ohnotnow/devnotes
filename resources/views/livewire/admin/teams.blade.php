<div>
    <div class="flex items-start gap-3">
        <div>
            <flux:heading size="xl" level="1">Teams</flux:heading>
            <flux:text class="mt-2">Teams tune whose search surfaces which notes - they never hide anything from anyone.</flux:text>
        </div>
        <flux:spacer />
        <flux:modal.trigger name="team-editor">
            <flux:button variant="primary" icon="plus" wire:click="openCreate">Add team</flux:button>
        </flux:modal.trigger>
    </div>

    <flux:table class="mt-6">
        <flux:table.columns>
            <flux:table.column>Team</flux:table.column>
            <flux:table.column>Members</flux:table.column>
            <flux:table.column>Notes</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($teams as $team)
                <flux:table.row wire:key="team-{{ $team->id }}">
                    <flux:table.cell variant="strong">{{ $team->name }}</flux:table.cell>
                    <flux:table.cell>{{ $team->users_count }}</flux:table.cell>
                    <flux:table.cell>{{ $team->notes_count }}</flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:button size="sm" icon="pencil" wire:click="openEdit({{ $team->id }})">Rename</flux:button>
                        <flux:button size="sm" variant="danger" icon="trash" wire:click="openDelete({{ $team->id }})">Delete</flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <flux:modal name="team-editor" variant="flyout" aria-label="Team editor" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" level="2">{{ $editing['id'] ? "Rename {$editing['name']}" : 'Add team' }}</flux:heading>
                <flux:text class="mt-2">Members and notes keep their links when a team is renamed.</flux:text>
            </div>

            <flux:input wire:model="editing.name" label="Name" placeholder="sysadmins" autofocus />

            <div class="flex justify-end gap-3">
                <flux:button x-on:click="$flux.modal('team-editor').close()">Cancel</flux:button>
                <flux:button variant="primary" wire:click="save">Save team</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="team-delete" variant="flyout" aria-label="Confirm team deletion" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" level="2">Delete {{ $deletingTeam?->name }}?</flux:heading>
                <flux:text class="mt-2">Nobody loses any notes - the team's notes just start showing in every team's search, and its members keep their other teams.</flux:text>
            </div>

            <div class="flex justify-end gap-3">
                <flux:button x-on:click="$flux.modal('team-delete').close()" autofocus>Cancel</flux:button>
                <flux:button variant="danger" wire:click="deleteTeam">Delete team</flux:button>
            </div>
        </div>
    </flux:modal>
</div>

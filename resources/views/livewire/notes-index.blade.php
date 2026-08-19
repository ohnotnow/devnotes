<div>
    <div class="flex items-start gap-3">
        <div>
            <flux:heading size="xl" level="1">Notes</flux:heading>
            <flux:text class="mt-2">The team's shared pot of gotchas, fixes and lessons learned.</flux:text>
        </div>
        <flux:spacer />
        @can('admin')
            <flux:button title="Export" aria-label="Export" icon="arrow-down-tray" :href="route('admin.export')"></flux:button>
        @endcan
        <flux:button title="Create new note" aria-label="Create new note" icon="plus" wire:click="$dispatch('note-editor:create')"></flux:button>
    </div>

    <div class="mt-6 flex items-center gap-6">
        <flux:input
            icon="magnifying-glass"
            placeholder="Search notes..."
            aria-label="Search notes"
            clearable
            wire:model.live.debounce.300ms="search"
            class="max-w-md"
        />

        <flux:field variant="inline">
            <flux:switch wire:model.live="broader" aria-label="All teams" />
            <flux:label>All teams</flux:label>
        </flux:field>
    </div>

    <flux:table :paginate="$notes" class="mt-6">
        <flux:table.columns>
            <flux:table.column>Note</flux:table.column>
            <flux:table.column>Author</flux:table.column>
            <flux:table.column>Updated</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($notes as $note)
                <flux:table.row wire:key="note-{{ $note->id }}">
                    <flux:table.cell class="flex items-center gap-3">
                        <flux:badge color="zinc" size="sm" inset="top bottom">#{{ $note->code }}</flux:badge>
                        <flux:link :href="route('notes.show', $note)" wire:navigate>{{ $note->title }}</flux:link>
                        @foreach ($note->teams as $team)
                            <flux:badge :color="$team->colour()" size="sm" inset="top bottom">{{ $team->name }}</flux:badge>
                        @endforeach
                    </flux:table.cell>
                    <flux:table.cell>{{ $note->user->full_name }}</flux:table.cell>
                    <flux:table.cell>{{ $note->updated_at->diffForHumans() }}</flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    <livewire:note-form />
</div>

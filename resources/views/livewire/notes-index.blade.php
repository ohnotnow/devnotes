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
        <flux:button variant="primary" icon="plus" wire:click="$dispatch('note-editor:create')">New note</flux:button>
    </div>

    <div class="mt-6 flex items-center gap-6">
        <flux:input
            icon="magnifying-glass"
            placeholder="Search notes..."
            aria-label="Search notes"
            clearable
            wire:model.live.debounce.300ms="search"
            class="max-w-md min-w-0"
        />

        @if ($channelOptions->isNotEmpty())
            {{-- No aria-label on flux:pillbox: it lands on the roleless custom-element
                 wrapper and the trigger stays nameless. The sr-only label inside
                 flux:field wires up aria-labelledby correctly. --}}
            <flux:field class="min-w-64 shrink-0">
                <flux:label class="sr-only">Filter by channel</flux:label>
                <flux:pillbox wire:model.live="channels" multiple placeholder="All channels">
                    @foreach ($channelOptions as $channel)
                        <flux:pillbox.option value="{{ $channel->id }}" wire:key="filter-channel-{{ $channel->id }}">{{ $channel->name }}</flux:pillbox.option>
                    @endforeach
                </flux:pillbox>
            </flux:field>
        @endif
    </div>

    <div role="status" class="sr-only">{{ $notes->total() }} {{ Str::plural('note', $notes->total()) }} found</div>

    @if ($notes->isEmpty())
        <flux:text class="mt-6">
            @if ($search)
                No notes match your search.
                @if ($filtering)
                    Try clearing the channel filter to search every channel.
                @endif
            @else
                No notes here yet.
            @endif
        </flux:text>
    @else
        <flux:table :paginate="$notes" class="mt-6">
            <flux:table.columns>
                <flux:table.column>Note</flux:table.column>
                <flux:table.column class="max-md:hidden">Author</flux:table.column>
                <flux:table.column class="max-md:hidden">Updated</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($notes as $note)
                    <flux:table.row wire:key="note-{{ $note->id }}">
                        <flux:table.cell class="flex flex-wrap items-center gap-3 whitespace-normal">
                            <a href="{{ route('notes.show', $note) }}"><flux:badge color="zinc" size="sm" inset="top bottom">#{{ $note->code }}</flux:badge></a>
                            <flux:link :href="route('notes.show', $note)" wire:navigate>{{ $note->title }}</flux:link>
                            @foreach ($note->channels as $channel)
                                <flux:badge as="button" :color="$channel->colour()" size="sm" inset="top bottom" wire:click="filterChannel({{ $channel->id }})" aria-label="Filter by {{ $channel->name }}" title="Show only {{ $channel->name }} notes">{{ $channel->name }}</flux:badge>
                            @endforeach
                        </flux:table.cell>
                        <flux:table.cell class="max-md:hidden">{{ $note->user->full_name }}</flux:table.cell>
                        <flux:table.cell class="max-md:hidden">{{ $note->updated_at->diffForHumans() }}</flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <livewire:note-form />
</div>

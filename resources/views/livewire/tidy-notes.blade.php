<div>
    <div>
        <flux:heading size="xl" level="1">Tidy</flux:heading>
        <flux:text class="mt-2">Your notes, least read first - a quick scan for ones worth deleting.</flux:text>
    </div>

    @can('admin')
        <flux:field variant="inline" class="mt-6">
            <flux:switch wire:model.live="showAll" aria-label="Show all notes" />
            <flux:label>Show all notes</flux:label>
        </flux:field>
    @endcan

    <flux:table :paginate="$notes" class="mt-6">
        <flux:table.columns>
            <flux:table.column>Code</flux:table.column>
            <flux:table.column>Title</flux:table.column>
            @if ($showingAll)
                <flux:table.column>Author</flux:table.column>
            @endif
            <flux:table.column>Created</flux:table.column>
            <flux:table.column>Updated</flux:table.column>
            <flux:table.column>Last read</flux:table.column>
            <flux:table.column>Reads</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @if ($notes->isEmpty())
                <flux:table.row>
                    <flux:table.cell colspan="{{ $showingAll ? 8 : 7 }}">Nothing to tidy - you have no notes.</flux:table.cell>
                </flux:table.row>
            @endif
            @foreach ($notes as $note)
                <flux:table.row wire:key="note-{{ $note->id }}">
                    <flux:table.cell>
                        {{-- wire:key on the trigger: escape-closing a flyout can lose focus otherwise - see devnote #m37h6 --}}
                        <flux:badge
                            as="button"
                            color="zinc"
                            size="sm"
                            inset="top bottom"
                            wire:key="preview-{{ $note->id }}"
                            wire:click="preview({{ $note->id }})"
                            title="Preview #{{ $note->code }}"
                        >#{{ $note->code }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell variant="strong">
                        <flux:link as="button" wire:key="preview-title-{{ $note->id }}" wire:click="preview({{ $note->id }})">{{ $note->title }}</flux:link>
                    </flux:table.cell>
                    @if ($showingAll)
                        <flux:table.cell>{{ $note->user->full_name }}</flux:table.cell>
                    @endif
                    <flux:table.cell>{{ $note->created_at->diffForHumans() }}</flux:table.cell>
                    <flux:table.cell>{{ $note->updated_at->diffForHumans() }}</flux:table.cell>
                    <flux:table.cell>{{ $note->last_read_at?->diffForHumans() ?? 'never' }}</flux:table.cell>
                    <flux:table.cell>{{ $note->read_count }}</flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:button
                            size="sm"
                            variant="danger"
                            icon="trash"
                            wire:click="delete({{ $note->id }})"
                            wire:confirm="Delete note #{{ $note->code }}? It stays in the database and remains viewable from links in other notes - but it disappears from search, the notes list, and agents' digests. A developer can un-delete it later."
                            aria-label="Delete note #{{ $note->code }}"
                        ></flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    {{-- No aria-label on flux:modal - see note-form.blade.php for why. --}}
    <flux:modal name="note-preview" variant="flyout" class="md:w-[32rem]">
        @if ($previewNote)
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg" level="2">{{ $previewNote->title }}</flux:heading>
                    <flux:text class="mt-2">
                        #{{ $previewNote->code }}, by {{ $previewNote->user->full_name }},
                        created {{ $previewNote->created_at->diffForHumans() }}@if (! $previewNote->updated_at->equalTo($previewNote->created_at)), updated {{ $previewNote->updated_at->diffForHumans() }}@endif
                    </flux:text>
                    @if ($previewNote->teams->isNotEmpty())
                        <div class="mt-2 flex gap-2">
                            @foreach ($previewNote->teams as $team)
                                <flux:badge color="sky" size="sm">{{ $team->name }}</flux:badge>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="prose dark:prose-invert max-w-none">
                    {!! $previewNote->rendered_body !!}
                </div>

                {{-- autofocus gives the dialog an initial focus target on open -
                     without one, focus stays on <body> and screen readers get no
                     signal the flyout appeared. --}}
                <div class="flex justify-end">
                    <flux:button x-on:click="$flux.modal('note-preview').close()" autofocus>Close</flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>

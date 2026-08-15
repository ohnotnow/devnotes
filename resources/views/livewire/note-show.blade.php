<div>
    <div class="flex items-center gap-3">
        <flux:heading size="xl" level="1">{{ $note->title }}</flux:heading>
        <flux:badge
            as="button"
            color="zinc"
            icon:trailing="clipboard-document"
            x-data
            x-on:click="navigator.clipboard.writeText('#{{ $note->id }}'); $flux.toast('Copied #{{ $note->id }} to clipboard')"
            title="Copy #{{ $note->id }} for cross-referencing"
        >#{{ $note->id }}</flux:badge>
        <flux:spacer />
        <flux:button size="sm" icon="pencil-square" wire:click="$dispatch('note-editor:edit', { id: {{ $note->id }} })">Edit</flux:button>
        <flux:modal.trigger name="confirm-note-delete">
            <flux:button size="sm" variant="danger" icon="trash">Delete</flux:button>
        </flux:modal.trigger>
    </div>

    {{-- No aria-label on flux:modal - see note-form.blade.php for why. --}}
    <flux:modal name="confirm-note-delete" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" level="2">Delete note #{{ $note->id }}?</flux:heading>
                <flux:text class="mt-2">Anything referencing #{{ $note->id }} will point at nothing. The note itself stays in the database, so a developer can un-delete it later.</flux:text>
            </div>
            <div class="flex justify-end gap-3">
                <flux:button x-on:click="$flux.modal('confirm-note-delete').close()" autofocus>Cancel</flux:button>
                <flux:button variant="danger" wire:click="delete">Delete note</flux:button>
            </div>
        </div>
    </flux:modal>
    <flux:text class="mt-2">
        By {{ $note->user->full_name }},
        created {{ $note->created_at->diffForHumans() }}@if (! $note->updated_at->equalTo($note->created_at)), updated {{ $note->updated_at->diffForHumans() }}@endif
    </flux:text>

    <flux:separator class="my-6" />

    <div class="prose dark:prose-invert max-w-none">
        {!! $note->rendered_body !!}
    </div>

    <livewire:note-form />
</div>

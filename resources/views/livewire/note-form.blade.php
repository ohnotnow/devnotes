<flux:modal name="note-editor" variant="flyout" aria-label="Note editor" class="md:w-[32rem]">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg" level="2">{{ $editing['id'] ? "Edit note #{$editing['id']}" : 'New note' }}</flux:heading>
            <flux:text class="mt-2">Scrappy is fine. Markdown is welcome, and #id references link to other notes.</flux:text>
        </div>

        <flux:input wire:model="editing.title" label="Title" placeholder="Livewire flyout modals lose focus on close" autofocus />

        <flux:textarea wire:model="editing.body" label="Note" rows="12" placeholder="What happened, what fixed it, links to anything useful..." />

        <div class="flex justify-end gap-3">
            <flux:button x-on:click="$flux.modal('note-editor').close()">Cancel</flux:button>
            <flux:button variant="primary" wire:click="save">Save note</flux:button>
        </div>
    </div>
</flux:modal>

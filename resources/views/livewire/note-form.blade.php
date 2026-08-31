{{-- No aria-label on flux:modal: it lands on the roleless ui-modal wrapper,
     which fails axe (aria-prohibited-attr) and leaks phantom text into the
     closed page's reading order. The dialog cannot be named through the
     component API; the level-2 heading announces the purpose instead. --}}
<flux:modal name="note-editor" variant="flyout" class="md:w-[32rem]">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg" level="2">{{ $editing['id'] ? "Edit note #{$editing['id']}" : 'New note' }}</flux:heading>
            <flux:text class="mt-2">Scrappy is fine. Markdown is welcome, and #id references link to other notes.</flux:text>
        </div>

        <flux:input wire:model="editing.title" label="Title" placeholder="Livewire flyout modals lose focus on close" autofocus />

        <flux:textarea wire:model="editing.body" label="Note" rows="12" placeholder="What happened, what fixed it, links to anything useful..." />

        @if ($channels->isNotEmpty())
            <flux:checkbox.group wire:model="selectedChannelIds" label="Channels" description="Which channels' searches should surface this note. Untick everything for a note everyone's search sees.">
                @foreach ($channels as $channel)
                    <flux:checkbox :value="$channel->id" :label="$channel->name" wire:key="channel-{{ $channel->id }}" />
                @endforeach
            </flux:checkbox.group>
        @endif

        <div class="flex justify-end gap-3">
            <flux:button x-on:click="$flux.modal('note-editor').close()">Cancel</flux:button>
            <flux:button variant="primary" wire:click="save">Save note</flux:button>
        </div>
    </div>
</flux:modal>

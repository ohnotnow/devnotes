<div>
    <div class="flex items-start gap-3">
        <div>
            <flux:heading size="xl" level="1">Channels</flux:heading>
            <flux:text class="mt-2">Channels tune whose search surfaces which notes - they never hide anything from anyone.</flux:text>
        </div>
        <flux:spacer />
        <flux:modal.trigger name="channel-editor">
            <flux:button variant="primary" icon="plus" wire:click="openCreate">Add channel</flux:button>
        </flux:modal.trigger>
    </div>

    <flux:table class="mt-6">
        <flux:table.columns>
            <flux:table.column>Channel</flux:table.column>
            <flux:table.column>Subscribers</flux:table.column>
            <flux:table.column>Notes</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($channels as $channel)
                <flux:table.row wire:key="channel-{{ $channel->id }}">
                    <flux:table.cell variant="strong">{{ $channel->name }}</flux:table.cell>
                    <flux:table.cell>{{ $channel->subscribers_count }}</flux:table.cell>
                    <flux:table.cell>{{ $channel->notes_count }}</flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:dropdown>
                            <flux:button variant="subtle" size="sm" icon="ellipsis-horizontal" aria-label="More options for {{ $channel->name }}" />
                            <flux:menu>
                                <flux:menu.item icon="pencil-square" wire:click="openEdit({{ $channel->id }})">Rename</flux:menu.item>
                                <flux:menu.item icon="trash" variant="danger" wire:click="openDelete({{ $channel->id }})">Delete...</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    {{-- No aria-label on flux:modal - see note-form.blade.php for why. --}}
    <flux:modal name="channel-editor" variant="flyout" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" level="2">{{ $editing['id'] ? "Rename {$editing['name']}" : 'Add channel' }}</flux:heading>
                <flux:text class="mt-2">Members and notes keep their links when a channel is renamed.</flux:text>
            </div>

            <flux:input wire:model="editing.name" label="Name" placeholder="sysadmins" autofocus />

            <div class="flex justify-end gap-3">
                <flux:button x-on:click="$flux.modal('channel-editor').close()">Cancel</flux:button>
                <flux:button variant="primary" wire:click="save">Save channel</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="channel-delete" variant="flyout" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" level="2">Delete {{ $deletingChannel?->name }}?</flux:heading>
                <flux:text class="mt-2">Nobody loses any notes - the channel's notes just start showing in every search, and its members keep their other channels.</flux:text>
            </div>

            <div class="flex justify-end gap-3">
                <flux:button x-on:click="$flux.modal('channel-delete').close()" autofocus>Cancel</flux:button>
                <flux:button variant="danger" wire:click="deleteChannel">Delete channel</flux:button>
            </div>
        </div>
    </flux:modal>
</div>

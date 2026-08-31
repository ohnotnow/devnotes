<div class="max-w-lg space-y-8">
    <div>
        <flux:heading size="xl" level="1">Channels</flux:heading>
        <flux:text class="mt-2">Channels only tune search - nothing is ever hidden, and anyone can still read and edit every note.</flux:text>
    </div>

    <flux:checkbox.group wire:model="subscribedChannelIds" label="Channels I read" description="Search results come from these channels (plus notes with no channel at all).">
        @foreach ($channels as $channel)
            <flux:checkbox :value="$channel->id" :label="$channel->name" wire:key="read-channel-{{ $channel->id }}" />
        @endforeach
    </flux:checkbox.group>

    <flux:checkbox.group wire:model="defaultChannelIds" label="My new notes go to" description="Notes you capture default to these channels unless you pick others on the note.">
        @foreach ($channels as $channel)
            <flux:checkbox :value="$channel->id" :label="$channel->name" wire:key="default-channel-{{ $channel->id }}" />
        @endforeach
    </flux:checkbox.group>

    <div class="flex justify-end">
        <flux:button variant="primary" wire:click="save">Save channels</flux:button>
    </div>
</div>

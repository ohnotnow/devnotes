<div class="mx-auto max-w-md">
    <flux:heading size="xl" level="1">Change password</flux:heading>

    <div class="mt-6 space-y-6">
        <flux:input wire:model="currentPassword" label="Current password" type="password" autofocus />
        <flux:input wire:model="newPassword" label="New password" type="password" />
        <flux:input wire:model="newPassword_confirmation" label="Repeat new password" type="password" />

        <div class="flex justify-end">
            <flux:button variant="primary" wire:click="save">Change password</flux:button>
        </div>
    </div>
</div>

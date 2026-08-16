<div class="mx-auto max-w-md">
    <flux:heading size="xl" level="1">Change password</flux:heading>

    <flux:card class="mt-6">
        <div class="space-y-6">
            <flux:input wire:model="currentPassword" label="Current password" type="password" autofocus />
            <flux:input wire:model="newPassword" label="New password" type="password" />
            <flux:input wire:model="newPassword_confirmation" label="Repeat new password" type="password" />

            <flux:button variant="primary" wire:click="save">Change password</flux:button>
        </div>
    </flux:card>
</div>

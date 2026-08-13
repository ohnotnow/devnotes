<x-layouts.app>
    <div class="mx-auto max-w-md mt-16 text-center">
        <flux:heading size="xl">You're not on the list</flux:heading>
        <flux:text class="mt-4">
            Your login worked, but this app is limited to the development team.
            If you think you should have access, ask one of the admins to add you as a user.
        </flux:text>
        <flux:button :href="route('login')" variant="primary" class="mt-6">Back to login</flux:button>
    </div>
</x-layouts.app>

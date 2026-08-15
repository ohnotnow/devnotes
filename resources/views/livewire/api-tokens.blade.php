<div>
    <div class="flex items-start gap-3">
        <div>
            <flux:heading size="xl" level="1">API tokens</flux:heading>
            <flux:text class="mt-2">Tokens let the CLI and MCP clients act as you. Treat them like passwords.</flux:text>
        </div>
        <flux:spacer />
        <flux:modal.trigger name="token-create">
            <flux:button variant="primary" icon="plus">New token</flux:button>
        </flux:modal.trigger>
    </div>

    @if ($newPlainTextToken !== '')
        <flux:callout variant="success" icon="key" class="mt-6">
            <flux:callout.heading>Copy your new token now - you will not see it again</flux:callout.heading>
            <flux:callout.text>
                <span class="font-mono break-all">{{ $newPlainTextToken }}</span>
            </flux:callout.text>
            <x-slot name="actions">
                <flux:button
                    size="sm"
                    icon="clipboard-document"
                    x-data
                    {{-- {{ Js::from() }}, not @js(): Blade directives are not compiled inside component tag attributes. --}}
                    x-on:click="navigator.clipboard.writeText({{ Illuminate\Support\Js::from($newPlainTextToken) }}); $flux.toast('Token copied to clipboard')"
                >Copy</flux:button>
            </x-slot>
        </flux:callout>
    @endif

    <flux:table class="mt-6">
        <flux:table.columns>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>Created</flux:table.column>
            <flux:table.column>Last used</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @if ($tokens->isEmpty())
                <flux:table.row>
                    <flux:table.cell colspan="4">No tokens yet - create one to use the CLI or MCP clients.</flux:table.cell>
                </flux:table.row>
            @endif
            @foreach ($tokens as $token)
                <flux:table.row wire:key="token-{{ $token->id }}">
                    <flux:table.cell variant="strong">{{ $token->name }}</flux:table.cell>
                    <flux:table.cell>{{ $token->created_at->diffForHumans() }}</flux:table.cell>
                    <flux:table.cell>{{ $token->last_used_at?->diffForHumans() ?? 'never' }}</flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:button
                            size="sm"
                            variant="danger"
                            icon="trash"
                            wire:click="revoke({{ $token->id }})"
                            wire:confirm="Revoke the token '{{ $token->name }}'? Anything using it stops working."
                        >Revoke</flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    {{-- No aria-label on flux:modal - see note-form.blade.php for why. --}}
    <flux:modal name="token-create" variant="flyout" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" level="2">New API token</flux:heading>
                <flux:text class="mt-2">Name it after where it will live, like "work laptop cli".</flux:text>
            </div>

            <flux:input wire:model="tokenName" label="Name" placeholder="work laptop cli" autofocus />

            <div class="flex justify-end gap-3">
                <flux:button x-on:click="$flux.modal('token-create').close()">Cancel</flux:button>
                <flux:button variant="primary" wire:click="create">Create token</flux:button>
            </div>
        </div>
    </flux:modal>
</div>

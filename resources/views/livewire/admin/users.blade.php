<div>
    <div class="flex items-start gap-3">
        <div>
            <flux:heading size="xl" level="1">Users</flux:heading>
            <flux:text class="mt-2">Anyone listed here can sign in through SSO. Add someone by email and their details fill in on their first login.</flux:text>
        </div>
        <flux:spacer />
        <flux:button variant="primary" icon="plus" wire:click="openAdd">Add person</flux:button>
    </div>

    <flux:table class="mt-6">
        <flux:table.columns>
            <flux:table.column>Person</flux:table.column>
            <flux:table.column>Username</flux:table.column>
            <flux:table.column>Teams</flux:table.column>
            <flux:table.column>Notes</flux:table.column>
            <flux:table.column>Admin</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($users as $user)
                <flux:table.row wire:key="user-{{ $user->id }}">
                    <flux:table.cell variant="strong">
                        {{ $user->full_name !== '' ? $user->full_name : $user->email }}
                        @if ($user->full_name !== '')
                            <flux:text size="sm" inline class="ms-2">{{ $user->email }}</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $user->username !== '' ? $user->username : 'awaiting first login' }}</flux:table.cell>
                    <flux:table.cell>
                        <button
                            type="button"
                            class="cursor-pointer"
                            wire:click="openTeams({{ $user->id }})"
                            aria-label="Edit teams for {{ $user->full_name !== '' ? $user->full_name : $user->email }}"
                        >
                            @forelse ($user->teams as $team)
                                <flux:badge color="sky" size="sm" inset="top bottom">{{ $team->name }}</flux:badge>
                            @empty
                                <flux:badge color="zinc" size="sm" inset="top bottom">no teams</flux:badge>
                            @endforelse
                        </button>
                    </flux:table.cell>
                    <flux:table.cell>{{ $user->notes_count }}</flux:table.cell>
                    <flux:table.cell>
                        @if ($user->is(auth()->user()))
                            <flux:badge size="sm" color="blue">You</flux:badge>
                        @else
                            <flux:switch
                                :checked="(bool) $user->is_admin"
                                wire:change="toggleAdmin({{ $user->id }})"
                                aria-label="Admin access for {{ $user->full_name !== '' ? $user->full_name : $user->email }}"
                            />
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        @unless ($user->is(auth()->user()))
                            <flux:button
                                size="sm"
                                variant="danger"
                                icon="trash"
                                wire:click="openDelete({{ $user->id }})"
                                aria-label="Delete {{ $user->full_name !== '' ? $user->full_name : $user->email }}"
                            >Delete</flux:button>
                        @endunless
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    {{-- No aria-label on flux:modal - see note-form.blade.php for why. --}}
    <flux:modal name="user-add" variant="flyout" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" level="2">Add a person</flux:heading>
                <flux:text class="mt-2">Use their university email address, the one SSO reports. Everything else fills in when they first log in.</flux:text>
            </div>

            <flux:input wire:model="email" label="Email" type="email" placeholder="someone@example.ac.uk" autofocus />

            @if ($teams->isNotEmpty())
                <flux:checkbox.group wire:model="selectedTeamIds" label="Teams" description="Their search follows these teams, and their notes default here. They can fine-tune it themselves later.">
                    @foreach ($teams as $team)
                        <flux:checkbox :value="$team->id" :label="$team->name" wire:key="add-team-{{ $team->id }}" />
                    @endforeach
                </flux:checkbox.group>
            @endif

            <div class="flex justify-end gap-3">
                <flux:button x-on:click="$flux.modal('user-add').close()">Cancel</flux:button>
                <flux:button variant="primary" wire:click="add">Add</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="user-teams" variant="flyout" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" level="2">Teams for {{ $editingTeamsUser?->full_name !== '' ? $editingTeamsUser?->full_name : $editingTeamsUser?->email }}</flux:heading>
                <flux:text class="mt-2">Saving here resets their per-team fine-tuning to the simple case: read and post to every ticked team.</flux:text>
            </div>

            @if ($teams->isNotEmpty())
                <flux:checkbox.group wire:model="selectedTeamIds" label="Teams" aria-label="Teams for this user">
                    @foreach ($teams as $team)
                        <flux:checkbox :value="$team->id" :label="$team->name" wire:key="edit-team-{{ $team->id }}" />
                    @endforeach
                </flux:checkbox.group>
            @endif

            <div class="flex justify-end gap-3">
                <flux:button x-on:click="$flux.modal('user-teams').close()">Cancel</flux:button>
                <flux:button variant="primary" wire:click="saveTeams">Save teams</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="user-delete" variant="flyout" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg" level="2">Delete {{ $deletingUser?->full_name !== '' ? $deletingUser?->full_name : $deletingUser?->email }}?</flux:heading>
                <flux:text class="mt-2">Their notes need a new owner first - a note always has an owner.</flux:text>
            </div>

            <flux:select wire:model="transferToId" label="Transfer their notes to..." autofocus>
                @foreach ($users->reject(fn ($candidate) => $candidate->is($deletingUser)) as $candidate)
                    <flux:select.option value="{{ $candidate->id }}">
                        {{ $candidate->full_name !== '' ? $candidate->full_name : $candidate->email }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex justify-end gap-3">
                <flux:button x-on:click="$flux.modal('user-delete').close()">Cancel</flux:button>
                <flux:button variant="danger" wire:click="deleteUser">Transfer notes and delete</flux:button>
            </div>
        </div>
    </flux:modal>
</div>

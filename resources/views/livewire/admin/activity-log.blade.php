<div>
    <flux:heading size="xl" level="1">Activity</flux:heading>
    <flux:text class="mt-2">Who did what, note by note.</flux:text>

    <div class="mt-6 flex items-center gap-6">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            placeholder="Search by #code, title or person"
            aria-label="Search activity"
            clearable
            class="max-w-md"
        />
        <div class="flex items-center gap-2" role="group" aria-label="Filter by action">
            @foreach ($actionFilters as $filter)
                <flux:badge
                    as="button"
                    :color="$filter['colour']"
                    :icon="$filter['active'] ? 'check' : null"
                    aria-pressed="{{ $filter['active'] ? 'true' : 'false' }}"
                    wire:click="toggleAction('{{ $filter['value'] }}')"
                    wire:key="action-filter-{{ $filter['value'] }}"
                >{{ $filter['label'] }}</flux:badge>
            @endforeach
        </div>
    </div>

    <flux:table :paginate="$activities" class="mt-6">
        <flux:table.columns>
            <flux:table.column>When</flux:table.column>
            <flux:table.column>Who</flux:table.column>
            <flux:table.column>Action</flux:table.column>
            <flux:table.column>Activity</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($activities as $activity)
                <flux:table.row wire:key="activity-{{ $activity->id }}">
                    <flux:table.cell class="whitespace-nowrap">{{ $activity->created_at->format('Y-m-d H:i') }}</flux:table.cell>
                    <flux:table.cell>{{ $activity->user?->full_name ?? 'Deleted user' }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$activity->action->colour()">{{ $activity->action->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $activity->description }}</flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>

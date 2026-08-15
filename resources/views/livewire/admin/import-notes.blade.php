<div>
    <div>
        <flux:heading size="xl" level="1">Import</flux:heading>
        <flux:text class="mt-2">Upload a devnotes export file. You get a preview of what will happen before anything is imported.</flux:text>
    </div>

    <div class="mt-6 max-w-lg">
        <flux:file-upload wire:model="file" label="File to import">
            <flux:file-upload.dropzone heading="Drop the export file here or click to browse" text="A devnotes export .json file" />
        </flux:file-upload>
        @if ($file)
            <div class="mt-3">
                <flux:file-item :heading="$file->getClientOriginalName()" :size="$file->getSize()">
                    <x-slot name="actions">
                        <flux:file-item.remove wire:click="removeFile" aria-label="Remove file: {{ $file->getClientOriginalName() }}" />
                    </x-slot>
                </flux:file-item>
            </div>
        @endif
    </div>

    @if ($preview !== [])
        <div class="mt-8 space-y-8">
            <div class="space-y-2">
                <flux:text>
                    {{ $preview['new_count'] }} new {{ Str::plural('note', $preview['new_count']) }} will be imported,
                    creating {{ $preview['users_to_create'] }} {{ Str::plural('author', $preview['users_to_create']) }}
                    and {{ $preview['teams_to_create'] }} {{ Str::plural('team', $preview['teams_to_create']) }}.
                </flux:text>
                @if ($preview['identical_count'] > 0)
                    <flux:text>{{ $preview['identical_count'] }} {{ Str::plural('note', $preview['identical_count']) }} {{ $preview['identical_count'] === 1 ? 'is' : 'are' }} already here and unchanged - {{ $preview['identical_count'] === 1 ? 'it' : 'they' }} will be skipped.</flux:text>
                @endif
            </div>

            @if ($preview['collisions'] !== [])
                <div>
                    <flux:heading size="lg" level="2">Codes that will be re-minted</flux:heading>
                    <flux:text class="mt-2">These new notes share a code with a note already here, so they will arrive under a fresh code - check any prose that references the old one afterwards.</flux:text>
                    <ul class="mt-3 list-disc space-y-1 pl-5">
                        @foreach ($preview['collisions'] as $collision)
                            <li><flux:text>#{{ $collision['code'] }} - {{ $collision['title'] }}</flux:text></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($preview['matches'] !== [])
                <div>
                    <flux:heading size="lg" level="2">Changed since the file was made</flux:heading>
                    <flux:text class="mt-2">These notes exist in both places (matched by identity) but their content differs. Keep this install's version or take the file's, per note.</flux:text>

                    <flux:table class="mt-4">
                        <flux:table.columns>
                            <flux:table.column>Code</flux:table.column>
                            <flux:table.column>In this install</flux:table.column>
                            <flux:table.column>In the file</flux:table.column>
                            <flux:table.column>Differs in</flux:table.column>
                            <flux:table.column>Decision</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($preview['matches'] as $match)
                                <flux:table.row wire:key="match-{{ $match['ulid'] }}">
                                    <flux:table.cell variant="strong">#{{ $match['code'] }}</flux:table.cell>
                                    <flux:table.cell>{{ $match['existing_title'] }}</flux:table.cell>
                                    <flux:table.cell>{{ $match['incoming_title'] }}</flux:table.cell>
                                    <flux:table.cell>{{ implode(', ', $match['differs']) }}</flux:table.cell>
                                    <flux:table.cell>
                                        <flux:select wire:model="decisions.{{ $match['ulid'] }}" aria-label="Decision for #{{ $match['code'] }}">
                                            <flux:select.option value="skip">Keep this install's version</flux:select.option>
                                            <flux:select.option value="overwrite">Take the file's version</flux:select.option>
                                        </flux:select>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>
            @endif

            <flux:switch wire:model="createUsers" label="Create missing authors" description="Switched off, notes by unknown authors are assigned to you instead - handy for QA copies without creating login accounts." />

            <flux:button variant="primary" icon="arrow-up-tray" wire:click="import">Run import</flux:button>
        </div>
    @endif
</div>
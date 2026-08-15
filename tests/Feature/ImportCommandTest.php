<?php

use App\Models\Note;
use Illuminate\Support\Facades\Storage;

it('imports an export file, prints the report, and cleans up its working copy', function () {
    Storage::fake(config('filesystems.default'));

    $this->artisan('devnotes:import', ['file' => base_path('tests/fixtures/export-v1.json')])
        ->expectsOutputToContain('Imported: 3')
        ->assertSuccessful();

    expect(Note::withTrashed()->count())->toBe(3);
    expect(Storage::disk(config('filesystems.default'))->allFiles('imports'))->toBeEmpty();
    expect(file_exists(base_path('tests/fixtures/export-v1.json')))->toBeTrue();

    $this->artisan('devnotes:import', ['file' => base_path('tests/fixtures/export-v1.json')])
        ->expectsOutputToContain('Imported: 0')
        ->expectsOutputToContain('Skipped existing: aq2b3, abq4x, zde77')
        ->assertSuccessful();

    expect(Note::withTrashed()->count())->toBe(3);
});

it('reports codes that had to be re-minted', function () {
    Storage::fake(config('filesystems.default'));
    Note::factory()->create(['code' => 'abq4x', 'title' => 'A local stranger']);

    $this->artisan('devnotes:import', ['file' => base_path('tests/fixtures/export-v1.json')])
        ->expectsOutputToContain('Imported: 3')
        ->expectsOutputToContain('Re-minted: abq4x ->')
        ->assertSuccessful();

    expect(Note::withTrashed()->count())->toBe(4);
    $incomingNote = Note::where('title', 'How to install the puppet client on Rocky Linux')->sole();
    expect($incomingNote->code)->not->toBe('abq4x');
    expect(Note::where('code', 'abq4x')->sole()->title)->toBe('A local stranger');
});

it('fails loudly for a missing file and imports nothing', function () {
    Storage::fake(config('filesystems.default'));

    $this->artisan('devnotes:import', ['file' => '/nowhere/nothing.json'])
        ->expectsOutputToContain('File not found: /nowhere/nothing.json')
        ->assertFailed();

    expect(Note::withTrashed()->count())->toBe(0);
    expect(Storage::disk(config('filesystems.default'))->allFiles('imports'))->toBeEmpty();
});

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
        ->expectsOutputToContain('Skipped existing ids: 1, 4, 7')
        ->assertSuccessful();

    expect(Note::withTrashed()->count())->toBe(3);
});

it('fails loudly for a missing file and imports nothing', function () {
    Storage::fake(config('filesystems.default'));

    $this->artisan('devnotes:import', ['file' => '/nowhere/nothing.json'])
        ->expectsOutputToContain('File not found: /nowhere/nothing.json')
        ->assertFailed();

    expect(Note::withTrashed()->count())->toBe(0);
    expect(Storage::disk(config('filesystems.default'))->allFiles('imports'))->toBeEmpty();
});

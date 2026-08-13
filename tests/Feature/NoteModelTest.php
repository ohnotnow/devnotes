<?php

use App\Models\Note;
use App\Models\User;
use Database\Seeders\TestDataSeeder;

it('soft-deletes a note without touching other notes', function () {
    $noteToDelete = Note::factory()->create();
    $noteToKeep = Note::factory()->create();

    $noteToDelete->delete();

    expect(Note::find($noteToDelete->id))->toBeNull();
    expect(Note::withTrashed()->find($noteToDelete->id)->deleted_at)->not->toBeNull();
    expect(Note::find($noteToKeep->id))->not->toBeNull();
});

it('lists the notes a user has created', function () {
    $userWithNote = User::factory()->create();
    $userWithoutNote = User::factory()->create();
    $note = Note::factory()->create(['user_id' => $userWithNote->id]);

    expect($userWithNote->notes)->toHaveCount(1);
    expect($userWithNote->notes->first()->is($note))->toBeTrue();
    expect($userWithoutNote->notes)->toHaveCount(0);
});

it('seeds notes including a #id cross-reference', function () {
    $this->seed(TestDataSeeder::class);

    $originalNote = Note::where('title', 'Livewire flyout modals lose focus on close')->sole();
    $followUpNote = Note::where('title', 'More flyout modal focus history')->sole();

    expect(Note::count())->toBe(5);
    expect($followUpNote->body)->toContain("#{$originalNote->id}");
});

it('mass-assigns a note through the creator relationship', function () {
    $user = User::factory()->create();

    $note = $user->notes()->create(['title' => 'A title', 'body' => 'A body']);

    expect($note->fresh()->title)->toBe('A title');
    expect($note->fresh()->user->is($user))->toBeTrue();
});

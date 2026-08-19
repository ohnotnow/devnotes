<?php

use App\Models\Note;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

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

it('mints a code and a ulid when a note is created', function () {
    $note = Note::factory()->create();

    expect($note->code)->toMatch('/^[abcdefghjkmnpqrstuvwxyz23456789]{5}$/');
    expect(Str::isUlid($note->ulid))->toBeTrue();
});

it('keeps an explicitly supplied code and ulid instead of minting', function () {
    $note = Note::factory()->create([
        'code' => 'abq4x',
        'ulid' => '01K2LD1Y5EXAMPLE0000000000',
    ]);

    expect($note->fresh()->code)->toBe('abq4x');
    expect($note->fresh()->ulid)->toBe('01K2LD1Y5EXAMPLE0000000000');
});

it('refuses a duplicate code even when the original note is soft-deleted', function () {
    $trashedNote = Note::factory()->create(['code' => 'abq4x']);
    $trashedNote->delete();

    expect(fn () => Note::factory()->create(['code' => 'abq4x']))
        ->toThrow(QueryException::class);
    expect(Note::withTrashed()->where('code', 'abq4x')->count())->toBe(1);
});

it('mass-assigns a note through the creator relationship', function () {
    $user = User::factory()->create();

    $note = $user->notes()->create(['title' => 'A title', 'body' => 'A body']);

    expect($note->fresh()->title)->toBe('A title');
    expect($note->fresh()->user->is($user))->toBeTrue();
});

<?php

use App\Models\Note;
use Illuminate\Support\HtmlString;

it('escapes raw html in the body', function () {
    $note = Note::factory()->make(['body' => "Watch out for <script>alert('pwned')</script> in here."]);

    $html = (string) $note->rendered_body;

    expect($html)->not->toContain('<script>');
    expect($html)->toContain('&lt;script');
});

it('links #code cross-references to the referenced note', function () {
    $referencedNote = Note::factory()->create(['code' => 'abq4x']);
    $note = Note::factory()->make(['body' => 'For the original write-up see #abq4x and move on.']);

    $html = (string) $note->rendered_body;

    expect($html)->toContain(route('notes.show', $referencedNote));
    expect($html)->toContain('#abq4x</a>');
});

it('renders mentions of soft-deleted notes in amber, still linked', function () {
    // Fresh make() per render: rendered_body is an object-valued accessor, so
    // Laravel caches it per model instance - one instance would render once.
    $referencedNote = Note::factory()->create(['code' => 'abq4x']);
    $body = 'For the original write-up see #abq4x and move on.';

    expect((string) Note::factory()->make(['body' => $body])->rendered_body)->not->toContain('text-amber');

    $referencedNote->delete();

    $html = (string) Note::factory()->make(['body' => $body])->rendered_body;
    expect($html)->toContain('text-amber-600');
    expect($html)->toContain(route('notes.show', 'abq4x'));
    expect($html)->toContain('#abq4x</a>');
});

it('leaves wrong-shaped references as plain text', function () {
    $note = Note::factory()->make(['body' => 'Old style #15, too-short #abq4, too-long #abq4xy, tail-cased #abq4xY and shouty #ABQ4X stay plain.']);

    $html = (string) $note->rendered_body;

    expect($html)->not->toContain('<a');
    expect($html)->toContain('#15');
    expect($html)->toContain('#abq4xy');
    expect($html)->toContain('#abq4xY');
});

it('leaves #code inside code blocks and code spans unlinked', function () {
    $note = Note::factory()->make(['body' => "Run `touch #abq4x.txt` or:\n\n```\ngit log --grep '#abq4x'\n```"]);

    $html = (string) $note->rendered_body;

    expect($html)->toContain('<code>');
    expect($html)->not->toContain('<a');
    expect($html)->toContain('#abq4x');
});

it('renders the body as github flavoured markdown', function () {
    $note = Note::factory()->make(['body' => "A table trick that is ~~gone~~ fixed now.\n\n| One | Two |\n| --- | --- |\n| a | b |"]);

    expect($note->rendered_body)->toBeInstanceOf(HtmlString::class);

    $html = (string) $note->rendered_body;

    expect($html)->toContain('<del>gone</del>');
    expect($html)->toContain('<table>');
});

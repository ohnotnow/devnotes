<?php

use App\Models\Note;
use Illuminate\Support\HtmlString;

it('escapes raw html in the body', function () {
    $note = Note::factory()->make(['body' => "Watch out for <script>alert('pwned')</script> in here."]);

    $html = (string) $note->rendered_body;

    expect($html)->not->toContain('<script>');
    expect($html)->toContain('&lt;script');
});

it('links #id cross-references to the referenced note', function () {
    $note = Note::factory()->make(['body' => 'For the original write-up see #49 and move on.']);

    $html = (string) $note->rendered_body;

    expect($html)->toContain(route('notes.show', 49));
    expect($html)->toContain('#49</a>');
});

it('leaves #id inside code blocks and code spans unlinked', function () {
    $note = Note::factory()->make(['body' => "Run `touch #49.txt` or:\n\n```\ngit log --grep '#49'\n```"]);

    $html = (string) $note->rendered_body;

    expect($html)->toContain('<code>');
    expect($html)->not->toContain('<a');
    expect($html)->toContain('#49');
});

it('renders the body as github flavoured markdown', function () {
    $note = Note::factory()->make(['body' => "A table trick that is ~~gone~~ fixed now.\n\n| One | Two |\n| --- | --- |\n| a | b |"]);

    expect($note->rendered_body)->toBeInstanceOf(HtmlString::class);

    $html = (string) $note->rendered_body;

    expect($html)->toContain('<del>gone</del>');
    expect($html)->toContain('<table>');
});

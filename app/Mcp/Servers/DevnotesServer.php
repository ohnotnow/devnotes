<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AddNote;
use App\Mcp\Tools\GetNote;
use App\Mcp\Tools\SearchNotes;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('devnotes')]
#[Version('0.0.1')]
#[Instructions('Devnotes is a small dev team\'s shared pot of gotchas, fixes, and lessons learned - tiny markdown notes captured mid-session. When something behaves oddly (a flaky test, a puzzling error, surprising framework behaviour), call search-notes BEFORE debugging from scratch: a teammate has probably paid for that lesson already. When a session uncovers or solves something gnarly, suggest a devnote to the developer ("this feels worth a devnote?") and use add-note once they agree. Write each note for a total stranger, because the reader will be a future session with no memory of today: symptom, cause, fix, and breadcrumbs like exact error text and versions. Notes can reference each other as #code (a short five-character code like #abq4x); add-note returns the code so you can cite it. Search results are scoped to your teams by default - if a scoped search does not answer the question, retry it with broader: true to cover every team\'s notes.')]
class DevnotesServer extends Server
{
    protected array $tools = [
        AddNote::class,
        SearchNotes::class,
        GetNote::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}

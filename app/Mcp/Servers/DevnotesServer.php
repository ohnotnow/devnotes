<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AddNote;
use App\Mcp\Tools\GetNote;
use App\Mcp\Tools\SearchNotes;
use App\Mcp\Tools\UpdateNote;
use App\Models\Note;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\ServerContext;

#[Name('devnotes')]
#[Version('0.0.1')]
class DevnotesServer extends Server
{
    private const HABIT_TEXT = 'Devnotes is a small dev team\'s shared pot of gotchas, fixes, and lessons learned - tiny markdown notes captured mid-session. When something behaves oddly (a flaky test, a puzzling error, surprising framework behaviour), call search-notes BEFORE debugging from scratch: a teammate has probably paid for that lesson already. When a session uncovers or solves something gnarly, suggest a devnote to the developer ("this feels worth a devnote?") and use add-note once they agree. Write each note for a total stranger, because the reader will be a future session with no memory of today: symptom, cause, fix, and breadcrumbs like exact error text and versions. Notes can reference each other as #code (a short five-character code like #abq4x); add-note returns the code so you can cite it. Search results are scoped to your teams by default - if a scoped search does not answer the question, retry it with broader: true to cover every team\'s notes.';

    protected array $tools = [
        AddNote::class,
        SearchNotes::class,
        GetNote::class,
        UpdateNote::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];

    /**
     * The #[Instructions] attribute would beat this property, so the
     * instructions are set here instead and built per authenticated user.
     */
    public function createContext(): ServerContext
    {
        $this->instructions = $this->instructionsFor(User::findOrFail(auth()->id()));

        return parent::createContext();
    }

    private function instructionsFor(User $user): string
    {
        $notes = Note::inTeamsOf($user)->with('teams')->latest('updated_at')->take(10)->get();

        if ($notes->isEmpty()) {
            return self::HABIT_TEXT;
        }

        $lines = $notes->map(function (Note $note): string {
            // Absolute date, not diffForHumans(): clients cache instructions at
            // connect time, so a relative age would go stale and read as false.
            $meta = $note->teams->pluck('name')->push($note->updated_at->toDateString())->implode(', ');

            return "- #{$note->code} {$note->title} ({$meta})";
        });

        return self::HABIT_TEXT
            ."\n\nRecently updated notes:\n"
            .$lines->implode("\n")
            ."\n\nThe pot has ".Note::count().' '.Str::plural('note', Note::count()).' in total. Call get-note with a #code to read one in full, or search-notes to find others.';
    }
}

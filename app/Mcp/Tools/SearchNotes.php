<?php

namespace App\Mcp\Tools;

use App\Models\Note;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Search the team\'s devnotes for gotchas, fixes, and lessons learned. Word-based full-text search: multi-word queries match on words in any order, best matches first - no need for exact phrases. Returns id, title, and a short snippet per match; use get-note with an id for the full note.')]
class SearchNotes extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'query' => ['required', 'string'],
        ]);

        $results = Note::search($validated['query'])->take(20)->get();

        return Response::json($results->map(fn (Note $note): array => [
            'id' => $note->id,
            'title' => $note->title,
            'snippet' => Str::limit($note->body, 200),
        ])->all());
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Search terms matched against note titles and bodies.')
                ->required(),
        ];
    }
}

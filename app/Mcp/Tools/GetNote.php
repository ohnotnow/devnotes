<?php

namespace App\Mcp\Tools;

use App\Models\Note;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Fetch one devnote in full, including its raw markdown body. Accepts a note id as returned by search-notes or add-note, in either bare (49) or hash-prefixed (#49) form.')]
class GetNote extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'id' => ['required', 'string'],
        ]);

        $note = Note::find(ltrim($validated['id'], '#'));

        if (! $note) {
            return Response::error("No note found with id {$validated['id']}. Use search-notes to find the right id.");
        }

        return Response::json([
            'id' => $note->id,
            'title' => $note->title,
            'body' => $note->body,
            'author' => $note->user->full_name,
            'created_at' => $note->created_at,
            'updated_at' => $note->updated_at,
        ]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()
                ->description('The note id, with or without the leading #.')
                ->required(),
        ];
    }
}

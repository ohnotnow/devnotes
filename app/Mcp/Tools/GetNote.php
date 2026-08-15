<?php

namespace App\Mcp\Tools;

use App\Models\Note;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Fetch one devnote in full, including its raw markdown body. Accepts a note code as returned by search-notes or add-note, in either bare (abq4x) or hash-prefixed (#abq4x) form.')]
class GetNote extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $note = Note::where('code', ltrim($validated['code'], '#'))->first();

        if (! $note) {
            return Response::error("No note found with code {$validated['code']}. Use search-notes to find the right code.");
        }

        return Response::json([
            'code' => $note->code,
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
            'code' => $schema->string()
                ->description('The note code, with or without the leading #.')
                ->required(),
        ];
    }
}

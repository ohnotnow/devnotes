<?php

namespace App\Mcp\Tools;

use App\Models\Note;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Capture a new devnote: a small markdown note recording a gotcha, fix, or lesson learned. Returns the new note id, which can be cited as #id in other notes and chat.')]
class AddNote extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        $note = Note::create([
            ...$validated,
            'user_id' => $request->user()->id,
        ]);

        return Response::json([
            'id' => $note->id,
            'title' => $note->title,
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
            'title' => $schema->string()
                ->description('A short, searchable title for the note.')
                ->required(),
            'body' => $schema->string()
                ->description('The note body as markdown. Write for a stranger: symptom, cause, fix, breadcrumbs.')
                ->required(),
        ];
    }
}

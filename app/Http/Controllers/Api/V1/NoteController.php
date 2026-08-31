<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NoteResource;
use App\Models\Note;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function index(Request $request)
    {
        $notes = $request->filled('search')
            ? Note::searchScoped($request->user(), $request->input('search'), $request->boolean('broader'))->paginate(20)
            : Note::with('user')->when(! $request->boolean('broader'), fn ($query) => $query->inChannelsOf($request->user()))->latest()->paginate(20);

        return NoteResource::collection($notes);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'channel_ids' => ['sometimes', 'array'],
            'channel_ids.*' => ['integer', 'exists:channels,id'],
        ]);

        $note = $request->user()->notes()->create($request->only(['title', 'body']));
        $note->assignChannels($data['channel_ids'] ?? null);

        return new NoteResource($note);
    }

    public function show(Note $note)
    {
        $note->incrementReadCount();

        return new NoteResource($note);
    }

    public function update(Request $request, Note $note)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'channel_ids' => ['sometimes', 'array'],
            'channel_ids.*' => ['integer', 'exists:channels,id'],
        ]);

        $note->update($request->only(['title', 'body']));

        if (array_key_exists('channel_ids', $data)) {
            $note->channels()->sync($data['channel_ids']);
        }

        return new NoteResource($note);
    }

    public function destroy(Note $note)
    {
        $note->delete();

        return response()->noContent();
    }
}

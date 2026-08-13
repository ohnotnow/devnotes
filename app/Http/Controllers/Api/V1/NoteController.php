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
            ? Note::search($request->input('search'))->query(fn ($query) => $query->with('user'))->paginate(20)
            : Note::with('user')->latest()->paginate(20);

        return NoteResource::collection($notes);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        $note = $request->user()->notes()->create($data);

        return new NoteResource($note);
    }

    public function show(Note $note)
    {
        return new NoteResource($note);
    }

    public function update(Request $request, Note $note)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ]);

        $note->update($data);

        return new NoteResource($note);
    }

    public function destroy(Note $note)
    {
        $note->delete();

        return response()->noContent();
    }
}

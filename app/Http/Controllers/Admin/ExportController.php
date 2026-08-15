<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Note;
use Illuminate\Http\JsonResponse;

class ExportController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(Note::exportPayload(), 200, [
            'Content-Disposition' => 'attachment; filename="devnotes-export-'.now()->toDateString().'.json"',
        ], Note::EXPORT_JSON_FLAGS);
    }
}

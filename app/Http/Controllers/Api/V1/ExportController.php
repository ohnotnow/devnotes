<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Note;
use Illuminate\Http\JsonResponse;

class ExportController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(Note::exportPayload(), 200, [], Note::EXPORT_JSON_FLAGS);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TtsAttentionGuide;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttentionGuideController extends Controller
{
    /**
     * Return all active attention guides as a JSON array.
     * Used by Flutter to sync server-defined guides.
     */
    public function index(Request $request): JsonResponse
    {
        $guides = TtsAttentionGuide::where('is_active', true)
            ->orderBy('id')
            ->get()
            ->map(fn($g) => [
                'id'          => $g->id,
                'text'        => $g->text,
                'interval'    => $g->interval_ms,   // Flutter model expects int milliseconds
                'language'    => $g->language,
                'speaker'     => $g->speaker,
                'engine'      => $g->engine,
                'speed'       => $g->speed,
            ]);

        return response()->json(['data' => $guides]);
    }
}

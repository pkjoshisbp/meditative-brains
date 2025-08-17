<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TrialEvent;
use Illuminate\Http\Request;

class TrialAnalyticsController extends Controller
{
    public function summary(Request $request)
    {
        $days = (int)$request->query('days', 30);
        $since = now()->subDays($days);
        $events = TrialEvent::where('created_at','>=',$since)->get();
        $grouped = $events->groupBy('event_type')->map->count();
        return response()->json([
            'window_days' => $days,
            'counts' => $grouped,
            'total' => $events->count(),
        ]);
    }
}

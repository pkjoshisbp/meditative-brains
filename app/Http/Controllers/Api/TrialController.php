<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TrialController extends Controller
{
    public function status(Request $request)
    {
        $user = $request->user();
        $trialSub = $user->subscriptions()->where('is_trial',true)->where('status','active')->orderByDesc('ends_at')->first();
        if (!$trialSub) {
            return response()->json([
                'is_trial' => false,
                'role' => $user->role,
            ]);
        }
        return response()->json([
            'is_trial' => true,
            'plan' => $trialSub->plan_type,
            'ends_at' => $trialSub->ends_at,
            'days_remaining' => $trialSub->daysRemaining(),
            'role' => $user->role,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GenerationUsageController extends Controller
{
    private function resolveGenerationLimit(?User $user): int
    {
        if (! $user) {
            return 3;
        }

        $plan = strtolower((string) ($user->plan ?? 'free'));

        return match ($plan) {
            'basic' => 50,
            'pro' => 200,
            // Storage column may be smaller, but runtime enforcement for School should still use 1000.
            'school' => 1000,
            default => max(0, (int) ($user->quiz_generation_limit ?? 3)),
        };
    }

    public function consume(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthorized.',
            ], 401);
        }

        if (($user->role ?? '') !== 'teacher') {
            return response()->json([
                'message' => 'Only teacher accounts can consume generation usage.',
            ], 403);
        }

        $limit = $this->resolveGenerationLimit($user);
        $used = max(0, (int) ($user->quizzes_used ?? 0));

        if ($limit > 0 && $used >= $limit) {
            return response()->json([
                'message' => "You have reached your plan limit ({$limit} generations). Upgrade to continue.",
                'quiz_generation_limit' => $limit,
                'quizzes_used' => $used,
            ], 403);
        }

        $nextUsed = $limit > 0 ? min($limit, $used + 1) : $used + 1;
        $user->quizzes_used = $nextUsed;
        $user->save();

        return response()->json([
            'message' => 'Generation usage updated.',
            'quiz_generation_limit' => $limit,
            'quizzes_used' => $nextUsed,
        ]);
    }
}

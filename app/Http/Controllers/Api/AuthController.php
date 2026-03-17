<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class AuthController extends Controller
{
    private array $blockedTempEmailDomains = [
        'mailinator.com',
        'tempmail.com',
        '10minutemail.com',
        'guerrillamail.com',
        'yopmail.com',
        'trashmail.com',
        'dispostable.com',
        'sharklasers.com',
    ];

    private function resolvePlanTier(?string $plan): string
    {
        return match ($plan) {
            'basic', 'pro', 'school' => $plan,
            default => 'trial', // Maps legacy "free" to trial
        };
    }

    private function applyPlanCapabilities(?User $user): ?User
    {
        if (!$user) {
            return $user;
        }

        $planTier = $this->resolvePlanTier($user->plan);
        if ($planTier === 'basic') {
            $user->quiz_generation_limit = 50;
            $user->max_questions_per_quiz = 50;
        } elseif ($planTier === 'pro') {
            $user->quiz_generation_limit = 200;
            $user->max_questions_per_quiz = 50;
        } elseif ($planTier === 'school') {
            $user->quiz_generation_limit = 1000;
            $user->max_questions_per_quiz = 50;
        }

        return $user;
    }

    private function isTemporaryEmail(string $email): bool
    {
        $domain = strtolower((string) substr(strrchr($email, '@') ?: '', 1));
        return in_array($domain, $this->blockedTempEmailDomains, true);
    }

    public function register(Request $request): JsonResponse
    {
        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'fullname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['nullable', Rule::in(['admin', 'teacher', 'student'])],
        ]);

        if ($this->isTemporaryEmail($validated['email'])) {
            return response()->json([
                'message' => 'Temporary email addresses are not allowed.',
            ], 422);
        }

        try {
            $payload = DB::transaction(function () use ($request, $validated) {
                $user = User::create([
                    'fullname' => $validated['fullname'],
                    'email' => $validated['email'],
                    'password' => $validated['password'],
                    'role' => $validated['role'] ?? 'teacher',
                    'plan' => 'free',
                    'quiz_generation_limit' => 3,
                    'quizzes_used' => 0,
                    'max_questions_per_quiz' => 10,
                ]);

                Auth::login($user);
                $request->session()->regenerate();

                $authedUser = $request->user();

                return [
                    'user' => $this->applyPlanCapabilities($authedUser),
                    'plan_tier' => $this->resolvePlanTier($authedUser?->plan),
                ];
            });

            return response()->json([
                'message' => 'Registration successful.',
                'user' => $payload['user'],
                'plan_tier' => $payload['plan_tier'],
            ], 201);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Registration failed due to a server configuration issue. Please try again shortly.',
            ], 500);
        }
    }

    public function registerStudent(Request $request): JsonResponse
    {
        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'firstname' => ['required', 'string', 'max:255'],
            'middlename' => ['nullable', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'join_code' => ['required', 'string', 'exists:classrooms,join_code'],
        ]);

        $classroom = \App\Models\Classroom::where('join_code', $validated['join_code'])->first();

        if ($classroom->invite_expires_at && now()->isAfter($classroom->invite_expires_at)) {
            return response()->json([
                'message' => 'This invite link or join code has expired. Please ask your teacher for a new one.',
            ], 422);
        }

        $fullname = trim($validated['firstname'] . ' ' . ($validated['middlename'] ?? '') . ' ' . $validated['lastname']);
        $fullname = str_replace('  ', ' ', $fullname);

        try {
            $user = DB::transaction(function () use ($request, $validated, $classroom, $fullname) {
                $user = User::create([
                    'fullname' => $fullname,
                    'email' => $validated['email'],
                    'password' => $validated['password'],
                    'role' => 'student',
                    'plan' => 'free',
                ]);

                $classroom->students()->attach($user->id);

                Auth::login($user);
                $request->session()->regenerate();

                return $request->user();
            });

            return response()->json([
                'message' => 'Student registered and enrolled successfully.',
                'user' => $user,
            ], 201);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Student registration failed due to a server configuration issue. Please try again shortly.',
            ], 500);
        }
    }

    public function login(Request $request): JsonResponse
    {
        $request->merge([
            'email' => mb_strtolower(trim((string) $request->input('email'))),
        ]);

        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 422);
        }

        $request->session()->regenerate();

        return response()->json([
            'message' => 'Login successful.',
            'user' => $this->applyPlanCapabilities($request->user()),
            'plan_tier' => $this->resolvePlanTier($request->user()?->plan),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logout successful.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $this->applyPlanCapabilities($user),
            'plan_tier' => $this->resolvePlanTier($user?->plan),
        ]);
    }
}

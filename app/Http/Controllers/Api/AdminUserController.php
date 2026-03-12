<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin', 403, 'Forbidden');
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        $users = User::query()
            ->where('role', '!=', 'admin')
            ->withCount('quizzes')
            ->latest()
            ->get([
                'id',
                'fullname',
                'email',
                'plan',
                'role',
                'created_at',
            ]);

        return response()->json($users);
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeAdmin($request);

        if ($user->role === 'admin') {
            return response()->json(['error' => 'Admin accounts cannot be edited here.'], 422);
        }

        $validated = $request->validate([
            'fullname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'plan' => ['nullable', Rule::in(['free', 'basic', 'pro', 'school'])],
            'role' => ['nullable', Rule::in(['admin', 'teacher', 'student'])],
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user->fresh(['quizzes']),
        ]);
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorizeAdmin($request);

        if ($user->role === 'admin') {
            return response()->json(['error' => 'Admin accounts cannot be deleted here.'], 422);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }
}

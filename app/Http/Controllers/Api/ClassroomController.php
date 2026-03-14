<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClassroomController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        if ($user->role === 'student') {
            return response()->json(
                $user->enrolledClassrooms()->withCount('students')->with('teacher:id,fullname')->latest()->get()
            );
        }

        return response()->json(
            $user->classrooms()->withCount('students')->latest()->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'room' => 'nullable|string|max:255',
            'schedule' => 'nullable|string|max:255',
        ]);

        $classroom = $request->user()->classrooms()->create([
            'name' => $validated['name'],
            'room' => $validated['room'],
            'schedule' => $validated['schedule'],
            'join_code' => strtoupper(Str::random(6)),
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Classroom created successfully',
            'classroom' => $classroom->loadCount('students'),
        ], 201);
    }

    public function show(Classroom $classroom)
    {
        if (auth()->id() !== $classroom->user_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json($classroom->load(['students', 'assignments.quiz']));
    }

    public function destroy(Classroom $classroom)
    {
        if (auth()->id() !== $classroom->user_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $classroom->delete();

        return response()->json([
            'message' => 'Classroom deleted successfully',
        ]);
    }

    public function updateInviteExpiration(Request $request, Classroom $classroom)
    {
        if (auth()->id() !== $classroom->user_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'expires_at' => 'nullable|date|after:now',
        ]);

        $classroom->update([
            'invite_expires_at' => $validated['expires_at'],
        ]);

        return response()->json([
            'message' => 'Invite expiration updated successfully',
            'invite_expires_at' => $classroom->invite_expires_at,
        ]);
    }

    public function joinByCode(Request $request)
    {
        $validated = $request->validate([
            'join_code' => 'required|string|size:6',
        ]);

        $classroom = Classroom::where('join_code', strtoupper($validated['join_code']))->first();

        if (!$classroom) {
            return response()->json(['message' => 'Invalid classroom code. Please check and try again.'], 404);
        }

        if (!$classroom->is_active) {
            return response()->json(['message' => 'This classroom is no longer active.'], 403);
        }

        $user = $request->user();

        if ($user->enrolledClassrooms()->where('classroom_id', $classroom->id)->exists()) {
            return response()->json(['message' => 'You are already enrolled in this classroom.'], 409);
        }

        $user->enrolledClassrooms()->attach($classroom->id);

        return response()->json([
            'message' => "Successfully joined {$classroom->name}!",
            'classroom' => $classroom->loadCount('students'),
        ]);
    }
}

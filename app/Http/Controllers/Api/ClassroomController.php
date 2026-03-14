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
        return response()->json(
            $request->user()->classrooms()->withCount('students')->latest()->get()
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
}

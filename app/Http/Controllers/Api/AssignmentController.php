<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Classroom;
use App\Models\Quiz;
use App\Models\Assignment;

class AssignmentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        if ($user->role === 'student') {
            $classroomIds = $user->enrolledClassrooms()->pluck('classrooms.id');
            
            $assignments = Assignment::whereIn('classroom_id', $classroomIds)
                ->with(['quiz:id,title,topic', 'classroom:id,name,user_id', 'classroom.teacher:id,fullname'])
                ->latest()
                ->get();
                
            return response()->json($assignments);
        }

        return response()->json([]);
    }

    public function store(Request $request, Classroom $classroom)
    {
        if (auth()->id() !== $classroom->user_id) {
            return response()->json(['error' => 'Unauthorized. Must be the classroom owner.'], 403);
        }

        $validated = $request->validate([
            'quiz_id' => 'required|exists:quizzes,id',
            'deadline_at' => 'nullable|date|after:now',
            'is_randomized' => 'boolean',
            'anti_cheat_mode' => 'boolean'
        ]);

        $quiz = Quiz::findOrFail($validated['quiz_id']);
        
        if (auth()->id() !== $quiz->user_id) {
            return response()->json(['error' => 'Unauthorized. Must be the quiz owner.'], 403);
        }

        $assignment = Assignment::create([
            'classroom_id' => $classroom->id,
            'quiz_id' => $quiz->id,
            'deadline_at' => $validated['deadline_at'] ?? null,
            'is_randomized' => $validated['is_randomized'] ?? false,
            'anti_cheat_mode' => $validated['anti_cheat_mode'] ?? false,
        ]);

        return response()->json([
            'message' => 'Assignment created successfully',
            'assignment' => $assignment->load('quiz')
        ], 201);
    }
    public function show(Assignment $assignment, Request $request)
    {
        $user = $request->user();
        
        // Ensure student is enrolled in the classroom
        if ($user->role === 'student') {
            $isEnrolled = $user->enrolledClassrooms()->where('classrooms.id', $assignment->classroom_id)->exists();
            if (!$isEnrolled) {
                return response()->json(['error' => 'You are not enrolled in this classroom.'], 403);
            }
        } else if ($user->role === 'teacher') {
             if ($user->id !== $assignment->classroom->user_id) {
                return response()->json(['error' => 'Unauthorized.'], 403);
            }
        }

        return response()->json($assignment->load(['quiz.questions', 'classroom.teacher:id,fullname']));
    }
}

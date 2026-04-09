<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Assignment;
use App\Models\Submission;

class SubmissionController extends Controller
{
    public function store(Request $request, Assignment $assignment)
    {
        $user = $request->user();
        
        // Ensure student is enrolled
        $isEnrolled = $user->enrolledClassrooms()
            ->wherePivot('status', 'approved')
            ->where('classrooms.id', $assignment->classroom_id)
            ->exists();
        if (!$isEnrolled) {
            return response()->json(['error' => 'You are not enrolled in this classroom.'], 403);
        }

        // Check if already submitted
        if ($assignment->submissions()->where('user_id', $user->id)->exists()) {
             return response()->json(['error' => 'You have already submitted this assignment.'], 409);
        }

        $validated = $request->validate([
            'answers' => 'required|array',
        ]);

        // Basic auto-grading logic
        $quiz = $assignment->quiz()->with('questions')->first();
        $totalPoints = 0;
        $earnedPoints = 0;
        $gradedAnswers = [];

        foreach ($quiz->questions as $question) {
            $questionPoints = max(1, (int) ($question->points ?? 1));
            $totalPoints += $questionPoints;
            // Student answer might be an index if it's multiple choice, but usually it's the text from current UI
            $studentAnswer = $validated['answers'][$question->id] ?? null;
            $isCorrect = false;
            
            if ($studentAnswer !== null) {
                if (trim(strtolower($studentAnswer)) === trim(strtolower($question->correct_answer))) {
                    $earnedPoints += $questionPoints;
                    $isCorrect = true;
                }
            }
            
            $gradedAnswers[$question->id] = [
                'answer' => $studentAnswer,
                'is_correct' => $isCorrect,
                'correct_answer' => $question->correct_answer,
                'points' => $questionPoints,
                'earned_points' => $isCorrect ? $questionPoints : 0,
            ];
        }

        $finalScore = ($totalPoints > 0) ? ($earnedPoints / $totalPoints) * 100 : 0;

        $submission = Submission::create([
            'assignment_id' => $assignment->id,
            'user_id' => $user->id,
            'score' => $finalScore,
            'answers' => $gradedAnswers,
            'is_graded' => true,
        ]);

        return response()->json([
            'message' => 'Assignment submitted successfully!',
            'submission' => $submission
        ], 201);
    }
}

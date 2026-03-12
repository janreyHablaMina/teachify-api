<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\QuizService;
use Illuminate\Http\Request;
use App\Models\Quiz;

class QuizController extends Controller
{
    protected $quizService;

    public function __construct(QuizService $quizService)
    {
        $this->quizService = $quizService;
    }

    public function index(Request $request)
    {
        return response()->json(
            $request->user()->quizzes()->withCount('questions')->latest()->get()
        );
    }

    public function show(Quiz $quiz)
    {
        if (auth()->id() !== $quiz->user_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json($quiz->load('questions'));
    }

    public function generateFromUpload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,docx,pptx|max:10240', // 10MB limit
            'count' => 'required|integer|min:1|max:50',
            'types' => 'required|array',
        ]);

        try {
            $quiz = $this->quizService->generateFromPdf(
                $request->file('file'),
                $request->user()->id,
                [
                    'count' => $request->count,
                    'types' => $request->types,
                ]
            );

            return response()->json([
                'message' => 'Quiz generated successfully',
                'quiz' => $quiz,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}

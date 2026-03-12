<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\QuizService;
use App\Services\PdfService;
use Illuminate\Http\Request;
use App\Models\Quiz;

class QuizController extends Controller
{
    protected $quizService;
    protected $pdfService;

    public function __construct(QuizService $quizService, PdfService $pdfService)
    {
        $this->quizService = $quizService;
        $this->pdfService = $pdfService;
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

    public function destroy(Quiz $quiz)
    {
        if (auth()->id() !== $quiz->user_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $quiz->delete();

        return response()->json([
            'message' => 'Quiz deleted successfully',
        ]);
    }

    public function generateFromUpload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf|max:10240',
            'title' => 'nullable|string|max:255',
            'count' => 'required|integer|min:1|max:50',
            'types' => 'required|array',
        ]);

        try {
            $quiz = $this->quizService->generateFromPdf(
                $request->file('file'),
                $request->user()->id,
                [
                    'title' => filled($request->input('title')) ? $request->input('title') : null,
                    'count' => (int) $request->input('count', 10),
                    'types' => $request->input('types', []),
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

    public function exportPdf(Request $request, Quiz $quiz)
    {
        if (auth()->id() !== $quiz->user_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $includeAnswers = $request->boolean('include_answers', false);
        $quiz->load('questions');

        $contentLines = [];
        foreach ($quiz->questions as $index => $question) {
            $questionNumber = $index + 1;
            $contentLines[] = "Q{$questionNumber}: {$question->question_text}";

            if (is_array($question->options) && count($question->options) > 0) {
                foreach ($question->options as $optIndex => $option) {
                    $label = chr(65 + $optIndex);
                    $contentLines[] = "{$label}. {$option}";
                }
            }

            if ($includeAnswers) {
                $contentLines[] = "Answer: {$question->correct_answer}";
                if (!empty($question->explanation)) {
                    $contentLines[] = "Explanation: {$question->explanation}";
                }
            }

            $contentLines[] = "";
        }

        $title = $quiz->title . ($includeAnswers ? ' (With Answers)' : ' (Questions Only)');
        $pdfContent = $this->pdfService->generateFromHtml($title, implode("\n", $contentLines));

        return response($pdfContent)
            ->header('Content-Type', 'application/pdf')
            ->header(
                'Content-Disposition',
                'attachment; filename="' . str($quiz->title)->slug() . ($includeAnswers ? '-with-answers' : '-questions-only') . '.pdf"'
            );
    }
}

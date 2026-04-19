<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PdfService;
use App\Services\QuizService;
use App\Services\TeacherNotificationService;
use Illuminate\Http\Request;
use App\Models\Quiz;

class QuizController extends Controller
{
    protected $quizService;
    protected $pdfService;
    protected $notificationService;

    public function __construct(QuizService $quizService, PdfService $pdfService, TeacherNotificationService $notificationService)
    {
        $this->quizService = $quizService;
        $this->pdfService = $pdfService;
        $this->notificationService = $notificationService;
    }

    public function index(Request $request)
    {
        return response()->json(
            $request->user()->quizzes()->withCount('questions')->latest()->get()
        );
    }

    public function store(Request $request)
    {
        $user = $request->user();
        
        $request->validate([
            'title' => 'required|string|max:255',
            'topic' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:255',
            'questions' => 'required|array|min:1',
            'questions.*.type' => 'required|string',
            'questions.*.question' => 'required|string',
            'questions.*.answer' => 'required|string',
            'questions.*.options' => 'nullable|array',
            'questions.*.explanation' => 'nullable|string',
            'questions.*.points' => 'nullable|integer',
        ]);

        $quiz = Quiz::create([
            'user_id' => $user->id,
            'title' => $request->title,
            'topic' => $request->topic,
            'type' => $request->type ?? 'generated',
        ]);

        foreach ($request->questions as $questionData) {
            $quiz->questions()->create([
                'type' => $questionData['type'],
                'question_text' => $questionData['question'],
                'options' => $questionData['options'] ?? [],
                'correct_answer' => $questionData['answer'],
                'explanation' => $questionData['explanation'] ?? null,
                'points' => $questionData['points'] ?? 1,
            ]);
        }

        $this->notificationService->create($user, [
            'source_key' => 'free:ai:quiz-generated:' . $quiz->id,
            'title' => 'Quiz generated successfully',
            'message' => 'Your quiz "' . $quiz->title . '" is ready to review.',
            'category' => 'ai_activity',
            'event_type' => 'quiz_generated',
            'severity' => 'success',
        ]);
        $this->notificationService->deleteBySource($user, TeacherNotificationService::FREE_FIRST_QUIZ_REMINDER_SOURCE);

        return response()->json($quiz->load('questions'), 201);
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

        $user = auth()->user();
        $quiz->delete();
        
        $this->updateUserUsage($user);

        return response()->json([
            'message' => 'Quiz deleted successfully',
        ]);
    }

    public function duplicate(Quiz $quiz)
    {
        if (auth()->id() !== $quiz->user_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = auth()->user();
        if ($this->isLimitReached($user)) {
            return response()->json([
                'error' => "You have reached your plan limit ({$user->quiz_generation_limit} quiz generations). Upgrade to continue using Teachify AI.",
            ], 403);
        }

        $quiz->load('questions');

        $copy = Quiz::create([
            'user_id' => $user->id,
            'title' => $quiz->title . ' (Copy)',
            'topic' => $quiz->topic,
            'type' => $quiz->type,
        ]);

        foreach ($quiz->questions as $question) {
            $copy->questions()->create([
                'type' => $question->type,
                'question_text' => $question->question_text,
                'options' => $question->options,
                'correct_answer' => $question->correct_answer,
                'explanation' => $question->explanation,
                'points' => max(1, (int) ($question->points ?? 1)),
            ]);
        }

        $this->updateUserUsage($user);

        return response()->json([
            'message' => 'Quiz duplicated successfully',
            'quiz' => $copy->load('questions'),
        ]);
    }

    public function generateFromUpload(Request $request)
    {
        $user = $request->user();
        if ($this->isLimitReached($user)) {
            return response()->json([
                'error' => "You have reached your plan limit ({$user->quiz_generation_limit} quiz generations). Upgrade to continue using Teachify AI.",
            ], 403);
        }

        $plan = $user->plan ?? 'free';
        $allowedMimes = in_array($plan, ['basic', 'pro', 'school']) ? 'pdf,docx,pptx' : 'pdf';
        
        $request->validate([
            'file' => "required|file|mimes:{$allowedMimes}|max:5120",
            'title' => 'nullable|string|max:255',
            'count' => "required|integer|min:1|max:{$user->max_questions_per_quiz}",
            'types' => 'required|array',
            'type_counts' => 'nullable|array',
        ]);

        try {
            $quiz = $this->quizService->generateFromUpload(
                $request->file('file'),
                $userId = $user->id,
                [
                    'title' => filled($request->input('title')) ? $request->input('title') : null,
                    'count' => (int) $request->input('count', 10),
                    'types' => $request->input('types', []),
                    'type_counts' => $request->input('type_counts', []),
                ]
            );

            $this->updateUserUsage($user);
            $this->syncUsageNotifications($user);
            $this->notificationService->create($user, [
                'source_key' => 'free:ai:quiz-generated:' . $quiz->id,
                'title' => 'Quiz generated successfully',
                'message' => 'Your uploaded file was processed and quiz "' . $quiz->title . '" is ready.',
                'category' => 'ai_activity',
                'event_type' => 'quiz_generated',
                'severity' => 'success',
            ]);
            $this->notificationService->deleteBySource($user, TeacherNotificationService::FREE_FIRST_QUIZ_REMINDER_SOURCE);

            return response()->json([
                'message' => 'Quiz generated successfully',
                'quiz' => $quiz,
            ]);
        } catch (\Exception $e) {
            $this->notificationService->create($user, [
                'title' => 'Quiz generation failed',
                'message' => 'We could not generate your quiz. Please try again.',
                'category' => 'ai_activity',
                'event_type' => 'generation_failed',
                'severity' => 'critical',
            ]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function isLimitReached($user): bool
    {
        $plan = $user->plan ?? 'free';
        $isMonthlyPlan = in_array($plan, ['basic', 'pro', 'school'], true);
        
        $used = $isMonthlyPlan
            ? $user->quizzes()->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count()
            : $user->quizzes()->count();

        return $used >= ($user->quiz_generation_limit ?? 3);
    }

    private function updateUserUsage($user): void
    {
        $plan = $user->plan ?? 'free';
        $isMonthlyPlan = in_array($plan, ['basic', 'pro', 'school'], true);
        
        $user->quizzes_used = $isMonthlyPlan
            ? $user->quizzes()->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count()
            : $user->quizzes()->count();
        
        $user->save();
    }

    private function resolveGenerationLimit($user): int
    {
        $plan = strtolower((string) ($user->plan ?? 'free'));

        return match ($plan) {
            'basic' => 50,
            'pro' => 200,
            'school' => 1000,
            default => max(0, (int) ($user->quiz_generation_limit ?? 3)),
        };
    }

    private function syncUsageNotifications($user): void
    {
        $limit = $this->resolveGenerationLimit($user);
        $used = max(0, (int) ($user->quizzes_used ?? 0));
        $remaining = max(0, $limit - $used);

        if ($remaining === 1) {
            $this->notificationService->upsertBySource($user, 'free:usage:one-left', [
                'title' => 'You have 1 quiz generation left',
                'message' => 'Use it wisely, or upgrade to avoid interruptions.',
                'category' => 'plan',
                'event_type' => 'plan_updates',
                'severity' => 'warning',
            ]);
        }

        if ($remaining === 0) {
            $this->notificationService->upsertBySource($user, 'free:usage:limit-reached', [
                'title' => "You've reached your limit",
                'message' => 'Upgrade to continue generating more quizzes.',
                'category' => 'plan',
                'event_type' => 'limits_reached',
                'severity' => 'critical',
            ]);
        }

        if ($remaining <= 1) {
            $this->notificationService->upsertBySource($user, 'free:usage:upsell', [
                'title' => 'Upgrade to unlock unlimited quizzes',
                'message' => 'Upgrade to access more features and higher generation limits.',
                'category' => 'plan',
                'event_type' => 'suggestions',
                'severity' => 'info',
            ]);
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

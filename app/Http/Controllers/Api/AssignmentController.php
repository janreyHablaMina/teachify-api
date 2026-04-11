<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Classroom;
use App\Models\Quiz;
use App\Models\Assignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AssignmentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        if ($user->role === 'student') {
            $classroomIds = $user->enrolledClassrooms()
                ->wherePivot('status', 'approved')
                ->pluck('classrooms.id');
            
            $assignments = Assignment::whereIn('classroom_id', $classroomIds)
                ->with([
                    'quiz:id,title,topic',
                    'classroom:id,name,user_id',
                    'classroom.teacher:id,fullname',
                    'submissions' => function ($query) use ($user) {
                        $query->where('user_id', $user->id)->select('id', 'assignment_id', 'user_id', 'score', 'created_at');
                    },
                ])
                ->latest()
                ->get();

            $mapped = $assignments->map(function ($assignment) {
                $submission = $assignment->submissions->first();
                $payload = $assignment->toArray();
                $payload['has_submitted'] = (bool) $submission;
                $payload['submission'] = $submission
                    ? [
                        'id' => $submission->id,
                        'score' => $submission->score,
                        'submitted_at' => $submission->created_at,
                    ]
                    : null;
                unset($payload['submissions']);
                return $payload;
            });

            return response()->json($mapped);
        }

        return response()->json([]);
    }

    public function store(Request $request, Classroom $classroom)
    {
        try {
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

            $existingAssignment = Assignment::query()
                ->where('classroom_id', $classroom->id)
                ->where('quiz_id', $quiz->id)
                ->first();

            if ($existingAssignment) {
                return response()->json([
                    'message' => 'This quiz is already assigned to the selected classroom.',
                    'assignment' => $existingAssignment->load('quiz'),
                ], 422);
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
        } catch (Throwable $exception) {
            Log::error('Failed to create assignment via classroom route.', [
                'teacher_id' => auth()->id(),
                'classroom_id' => $classroom->id ?? null,
                'payload' => $request->all(),
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to assign quiz right now. Please try again.',
            ], 500);
        }
    }

    public function storeDirect(Request $request)
    {
        try {
            $validated = $request->validate([
                'classroom_id' => 'required|exists:classrooms,id',
                'quiz_id' => 'nullable|integer',
                'deadline_at' => 'nullable|date|after:now',
                'is_randomized' => 'boolean',
                'anti_cheat_mode' => 'boolean',
                'quiz_payload' => 'nullable|array',
                'quiz_payload.title' => 'nullable|string|max:255',
                'quiz_payload.topic' => 'nullable|string|max:255',
                'quiz_payload.type' => 'nullable|string|max:50',
                'quiz_payload.questions' => 'nullable|array',
                'quiz_payload.questions.*.type' => 'required_with:quiz_payload.questions|string|max:100',
                'quiz_payload.questions.*.question' => 'required_with:quiz_payload.questions|string',
                'quiz_payload.questions.*.choices' => 'nullable|array',
                'quiz_payload.questions.*.choices.*' => 'nullable|string',
                'quiz_payload.questions.*.answer' => 'nullable|string',
                'quiz_payload.questions.*.explanation' => 'nullable|string',
                'quiz_payload.questions.*.points' => 'nullable|integer|min:1|max:100',
            ]);

            $classroom = Classroom::findOrFail($validated['classroom_id']);
            if (auth()->id() !== $classroom->user_id) {
                return response()->json(['error' => 'Unauthorized. Must be the classroom owner.'], 403);
            }

            $quiz = null;
            if (!empty($validated['quiz_id'])) {
                $quiz = Quiz::find($validated['quiz_id']);
            }

            if (!$quiz) {
                $payload = $validated['quiz_payload'] ?? null;
                if (!$payload || !is_array($payload)) {
                    return response()->json([
                        'error' => 'The selected quiz id is invalid and no quiz payload was provided.',
                    ], 422);
                }

                $rawQuestions = $payload['questions'] ?? [];
                if (!is_array($rawQuestions) || count($rawQuestions) === 0) {
                    return response()->json([
                        'error' => 'Quiz payload must contain at least one question.',
                    ], 422);
                }

                $quiz = DB::transaction(function () use ($payload, $rawQuestions) {
                    $quizAttributes = [
                        'user_id' => auth()->id(),
                        'title' => $payload['title'] ?? 'Assigned Quiz',
                        'topic' => $payload['topic'] ?? null,
                        'type' => $payload['type'] ?? 'file',
                    ];
                    // Keep compatibility with environments where quizzes has additional required columns.
                    if (Schema::hasColumn('quizzes', 'difficulty')) {
                        $quizAttributes['difficulty'] = $payload['difficulty'] ?? 'medium';
                    }
                    if (Schema::hasColumn('quizzes', 'questions_count')) {
                        $quizAttributes['questions_count'] = is_array($rawQuestions) ? count($rawQuestions) : 0;
                    }

                    $createdQuiz = Quiz::create($quizAttributes);

                    foreach ($rawQuestions as $questionData) {
                        if (!is_array($questionData) || empty($questionData['question'])) {
                            continue;
                        }

                        $choices = isset($questionData['choices']) && is_array($questionData['choices'])
                            ? array_values(array_filter(array_map(
                                fn ($choice) => is_string($choice) ? trim($choice) : null,
                                $questionData['choices']
                            )))
                            : null;

                        $questionAttributes = [
                            'type' => $questionData['type'] ?? 'multiple_choice',
                            'question_text' => $questionData['question'],
                            'points' => max(1, min(100, (int) ($questionData['points'] ?? 1))),
                        ];

                        if (Schema::hasColumn('questions', 'options')) {
                            $questionAttributes['options'] = $choices ?: null;
                        } elseif (Schema::hasColumn('questions', 'choices')) {
                            $questionAttributes['choices'] = $choices ?: null;
                        }

                        if (Schema::hasColumn('questions', 'correct_answer')) {
                            $questionAttributes['correct_answer'] = $questionData['answer'] ?? null;
                        } elseif (Schema::hasColumn('questions', 'answer')) {
                            $questionAttributes['answer'] = $questionData['answer'] ?? null;
                        }

                        if (Schema::hasColumn('questions', 'explanation')) {
                            $questionAttributes['explanation'] = $questionData['explanation'] ?? null;
                        }

                        $createdQuiz->questions()->create($questionAttributes);
                    }

                    return $createdQuiz;
                });
            }
            
            if (auth()->id() !== $quiz->user_id) {
                return response()->json(['error' => 'Unauthorized. Must be the quiz owner.'], 403);
            }

            $existingAssignment = Assignment::query()
                ->where('classroom_id', $classroom->id)
                ->where('quiz_id', $quiz->id)
                ->first();

            if ($existingAssignment) {
                return response()->json([
                    'message' => 'This quiz is already assigned to the selected classroom.',
                    'assignment' => $existingAssignment->load('quiz'),
                ], 422);
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
                'assignment' => $assignment->load('quiz'),
            ], 201);
        } catch (Throwable $exception) {
            Log::error('Failed to create assignment via direct route.', [
                'teacher_id' => auth()->id(),
                'payload' => $request->all(),
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Unable to assign quiz right now. Please try again.',
                'error' => $exception->getMessage(),
            ], 500);
        }
    }

    public function show(Assignment $assignment, Request $request)
    {
        $user = $request->user();
        
        // Ensure student is enrolled in the classroom
        if ($user->role === 'student') {
            $isEnrolled = $user->enrolledClassrooms()
                ->wherePivot('status', 'approved')
                ->where('classrooms.id', $assignment->classroom_id)
                ->exists();
            if (!$isEnrolled) {
                return response()->json(['error' => 'You are not enrolled in this classroom.'], 403);
            }
        } else if ($user->role === 'teacher') {
             if ($user->id !== $assignment->classroom->user_id) {
                return response()->json(['error' => 'Unauthorized.'], 403);
            }
        }

        $assignment->load(['quiz.questions', 'classroom.teacher:id,fullname']);
        $submission = $assignment->submissions()
            ->where('user_id', $user->id)
            ->latest()
            ->first();
        $payload = $assignment->toArray();
        $payload['has_submitted'] = (bool) $submission;
        $payload['submission'] = $submission
            ? [
                'id' => $submission->id,
                'score' => $submission->score,
                'submitted_at' => $submission->created_at,
                'answers' => $submission->answers,
            ]
            : null;

        return response()->json($payload);
    }
}

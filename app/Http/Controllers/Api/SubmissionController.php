<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Assignment;
use App\Models\Submission;

class SubmissionController extends Controller
{
    private const STOP_WORDS = [
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from', 'has', 'have', 'he', 'her', 'his', 'i',
        'in', 'is', 'it', 'its', 'of', 'on', 'or', 'our', 'she', 'that', 'the', 'their', 'them', 'they', 'this',
        'to', 'was', 'were', 'will', 'with', 'you', 'your',
    ];

    private function normalizeType(?string $type): string
    {
        return str_replace(' ', '_', strtolower(trim((string) $type)));
    }

    private function normalizeText(?string $value): string
    {
        $text = strtolower(trim((string) $value));
        $text = preg_replace('/\s+/', ' ', $text) ?? '';
        return $text;
    }

    private function extractTokens(?string $value): array
    {
        $normalized = $this->normalizeText($value);
        if ($normalized === '') return [];
        $tokens = preg_split('/[^a-z0-9]+/i', $normalized) ?: [];
        $tokens = array_values(array_filter($tokens, function ($token) {
            return strlen($token) >= 3 && !in_array($token, self::STOP_WORDS, true);
        }));
        return array_values(array_unique($tokens));
    }

    private function parseEnumerationItems(?string $value): array
    {
        $text = trim((string) $value);
        if ($text === '') return [];
        $parts = preg_split('/[\n,;]+/', $text) ?: [];
        return array_values(array_filter(array_map(function ($part) {
            return trim((string) $part);
        }, $parts), function ($part) {
            return $part !== '';
        }));
    }

    private function isLowEffortEssay(?string $value): bool
    {
        $normalized = $this->normalizeText($value);
        if ($normalized === '') return true;

        if (preg_match('/\b(i\s*don\'?t\s*know|idk|no\s*idea|not\s*sure|guess|maybe)\b/i', $normalized)) {
            return true;
        }

        if (preg_match('/\b(ha)+\b/i', $normalized)) {
            return true;
        }

        $tokenCount = count($this->extractTokens($normalized));
        return $tokenCount < 5;
    }

    private function answersAreSimilar(string $left, string $right): bool
    {
        $a = $this->normalizeText($left);
        $b = $this->normalizeText($right);
        if ($a === '' || $b === '') return false;
        if ($a === $b) return true;
        if (str_contains($a, $b) || str_contains($b, $a)) return true;

        similar_text($a, $b, $percent);
        return $percent >= 75;
    }

    private function computeEssayRatio(?string $studentAnswer, ?string $correctAnswer): float
    {
        $student = trim((string) $studentAnswer);
        $correct = trim((string) $correctAnswer);
        if ($student === '' || $correct === '') return 0.0;
        if ($this->normalizeText($student) === $this->normalizeText($correct)) return 1.0;

        $studentTokens = $this->extractTokens($student);
        $correctTokens = $this->extractTokens($correct);
        if (count($correctTokens) === 0) return 0.0;
        if (count($studentTokens) === 0) return 0.0;

        $intersection = array_values(array_intersect($studentTokens, $correctTokens));
        if (count($intersection) < 2) return 0.0;

        $precision = count($intersection) / max(1, count($studentTokens));
        $recall = count($intersection) / max(1, count($correctTokens));
        if ($precision < 0.15 || $recall < 0.15) return 0.0;

        $f1 = ($precision + $recall) > 0 ? (2 * $precision * $recall) / ($precision + $recall) : 0.0;

        $lengthFactor = min(1.0, count($studentTokens) / max(8, (int) floor(count($correctTokens) * 0.5)));
        $ratio = (0.9 * $f1) + (0.1 * $lengthFactor);

        if ($ratio < 0.35) return 0.0;

        return max(0.0, min(1.0, $ratio));
    }

    private function scoreQuestion(string $type, ?string $studentAnswer, ?string $correctAnswer, int $questionPoints): array
    {
        $student = (string) ($studentAnswer ?? '');
        $correct = (string) ($correctAnswer ?? '');
        $normalizedStudent = $this->normalizeText($student);
        $normalizedCorrect = $this->normalizeText($correct);

        if ($normalizedStudent === '' || $normalizedCorrect === '') {
            return [0, false, 'No answer or no key answer available.'];
        }

        if ($type === 'enumeration') {
            $expectedItems = $this->parseEnumerationItems($correct);
            $studentItems = $this->parseEnumerationItems($student);

            if (count($expectedItems) === 0 || count($studentItems) === 0) {
                return [0, false, 'Enumeration answer missing expected or submitted items.'];
            }

            $matched = 0;
            $usedStudentIndexes = [];
            foreach ($expectedItems as $expected) {
                foreach ($studentItems as $index => $submitted) {
                    if (in_array($index, $usedStudentIndexes, true)) continue;
                    if ($this->answersAreSimilar($submitted, $expected)) {
                        $matched++;
                        $usedStudentIndexes[] = $index;
                        break;
                    }
                }
            }

            $ratio = $matched / max(1, count($expectedItems));
            $earned = (int) round($questionPoints * $ratio);
            return [
                max(0, min($questionPoints, $earned)),
                $matched === count($expectedItems),
                "Matched {$matched} of " . count($expectedItems) . ' expected items.',
            ];
        }

        if ($type === 'essay') {
            if ($this->isLowEffortEssay($student)) {
                return [0, false, 'Essay response appears low-effort or uncertain.'];
            }

            $ratio = $this->computeEssayRatio($student, $correct);
            $earned = (int) round($questionPoints * $ratio);
            return [
                max(0, min($questionPoints, $earned)),
                $ratio >= 0.95,
                'Essay semantic similarity score: ' . number_format($ratio * 100, 1) . '%.',
            ];
        }

        if ($normalizedStudent === $normalizedCorrect) {
            return [$questionPoints, true, 'Exact match.'];
        }

        return [0, false, 'Answer did not match expected response.'];
    }

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
            [$earnedForQuestion, $isCorrect, $gradingNote] = $this->scoreQuestion(
                $this->normalizeType($question->type),
                is_scalar($studentAnswer) ? (string) $studentAnswer : null,
                $question->correct_answer,
                $questionPoints
            );
            $earnedPoints += $earnedForQuestion;
            
            $gradedAnswers[$question->id] = [
                'answer' => $studentAnswer,
                'is_correct' => $isCorrect,
                'correct_answer' => $question->correct_answer,
                'points' => $questionPoints,
                'earned_points' => $earnedForQuestion,
                'grading_note' => $gradingNote,
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

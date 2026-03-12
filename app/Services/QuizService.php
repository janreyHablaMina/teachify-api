<?php

namespace App\Services;

use App\Models\Quiz;
use App\Models\Question;
use Smalot\PdfParser\Parser;
use Illuminate\Support\Facades\Log;

class QuizService
{
    protected $aiService;
    protected $pdfParser;

    public function __construct(AiService $aiService)
    {
        $this->aiService = $aiService;
        $this->pdfParser = new Parser();
    }

    /**
     * Generate a quiz from a PDF file
     */
    public function generateFromPdf($file, $userId, array $options)
    {
        if ($file->getClientOriginalExtension() !== 'pdf') {
            throw new \Exception("Currently only PDF files are supported for AI question generation.");
        }

        try {
            $pdf = $this->pdfParser->parseFile($file->getPathname());
            $text = $pdf->getText();

            if (empty(trim($text))) {
                throw new \Exception("Could not extract any text from the PDF file. It might be scanned or empty.");
            }

            // Limit text to avoid token limits (approx first 3000 words)
            $limitedText = implode(' ', array_slice(explode(' ', $text), 0, 3000));

            $questionCount = max(1, min((int) ($options['count'] ?? 10), 50));
            $allowedTypes = ['multiple_choice', 'true_false', 'short_answer', 'essay', 'enumeration'];
            $types = array_values(array_filter(
                (array) ($options['types'] ?? ['multiple_choice']),
                fn ($type) => in_array($type, $allowedTypes, true)
            ));
            if (count($types) === 0) {
                $types = ['multiple_choice'];
            }
            $typeList = implode(', ', $types);

            $prompt = "You are an educational expert. Based on the lesson content provided below, generate a quiz with exactly {$questionCount} questions. 
            Allowed question types: {$typeList}.
            Distribute questions across allowed types naturally.
            
            Format your response as a JSON array of objects with the following schema:
            [
              {
                \"question_text\": \"string\",
                \"type\": \"one of: {$typeList}\",
                \"options\": [\"string\", \"string\", \"string\", \"string\"] (exactly 4 options only if type is multiple_choice, otherwise null),
                \"correct_answer\": \"string (the correct option or answer text)\",
                \"explanation\": \"string (short educational explanation)\"
              }
            ]
            Return ONLY valid JSON. No markdown fences.

            Content:
            ---
            {$limitedText}
            ---";

            $aiResult = $this->aiService->generateRawResponse($prompt);
            
            // Clean up AI response if it contains markdown code blocks
            $json = preg_replace('/^```json\s*/', '', $aiResult);
            $json = preg_replace('/\s*```$/', '', $json);
            
            $decoded = json_decode($json, true);
            $questionsData = $decoded['questions'] ?? $decoded;

            if (!is_array($questionsData)) {
                Log::error("AI Quiz JSON Parsing Failed", ['raw' => $aiResult]);
                throw new \Exception("Failed to parse quiz questions from AI response.");
            }

            $quiz = Quiz::create([
                'user_id' => $userId,
                'title' => $options['title'] ?? 'Generated Quiz - ' . $file->getClientOriginalName(),
                'topic' => $options['topic'] ?? $file->getClientOriginalName(),
                'type' => 'file',
            ]);

            $createdCount = 0;
            foreach ($questionsData as $q) {
                if (!is_array($q) || empty($q['question_text']) || empty($q['correct_answer'])) {
                    continue;
                }

                $questionType = $q['type'] ?? $types[0];
                if (!in_array($questionType, $allowedTypes, true)) {
                    $questionType = $types[0];
                }

                $questionText = (string) $q['question_text'];
                $correctAnswer = (string) $q['correct_answer'];
                if (mb_strlen($correctAnswer) > 250) {
                    $correctAnswer = mb_substr($correctAnswer, 0, 250);
                }

                Question::create([
                    'quiz_id' => $quiz->id,
                    'type' => $questionType,
                    'question_text' => $questionText,
                    'options' => $questionType === 'multiple_choice' ? ($q['options'] ?? null) : null,
                    'correct_answer' => $correctAnswer,
                    'explanation' => $q['explanation'] ?? null,
                ]);
                $createdCount++;
            }

            if ($createdCount === 0) {
                throw new \Exception("AI returned no valid questions for this PDF. Please try again with a text-based PDF.");
            }

            return $quiz->load('questions');

        } catch (\Exception $e) {
            Log::error("Quiz Generation Failed: " . $e->getMessage());
            throw $e;
        }
    }
}

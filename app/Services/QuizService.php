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

            $questionCount = $options['count'] ?? 10;
            $type = $options['types'][0] ?? 'multiple_choice';

            $prompt = "You are an educational expert. Based on the lesson content provided below, generate a quiz with exactly {$questionCount} {$type} questions. 
            
            Format your response as a JSON array of objects with the following schema:
            [
              {
                \"question_text\": \"string (the question)\",
                \"type\": \"{$type}\",
                \"options\": [\"string\", \"string\", \"string\", \"string\"] (exactly 4 options if multiple_choice, else null),
                \"correct_answer\": \"string (the correct option or answer text)\",
                \"explanation\": \"string (short educational explanation)\"
              }
            ]

            Content:
            ---
            {$limitedText}
            ---";

            $aiResult = $this->aiService->generateRawResponse($prompt);
            
            // Clean up AI response if it contains markdown code blocks
            $json = preg_replace('/^```json\s*/', '', $aiResult);
            $json = preg_replace('/\s*```$/', '', $json);
            
            $questionsData = json_decode($json, true);

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

            foreach ($questionsData as $q) {
                Question::create([
                    'quiz_id' => $quiz->id,
                    'type' => $q['type'] ?? $type,
                    'question_text' => $q['question_text'],
                    'options' => $q['options'] ?? null,
                    'correct_answer' => $q['correct_answer'],
                    'explanation' => $q['explanation'] ?? null,
                ]);
            }

            return $quiz->load('questions');

        } catch (\Exception $e) {
            Log::error("Quiz Generation Failed: " . $e->getMessage());
            throw $e;
        }
    }
}

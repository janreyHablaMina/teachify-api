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
    public function generateFromUpload($file, $userId, array $options)
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($extension, ['pdf', 'docx', 'pptx'], true)) {
            throw new \Exception("Unsupported file format. Allowed formats are PDF, DOCX, and PPTX.");
        }

        try {
            $text = $this->extractTextFromFile($file->getPathname(), $extension);

            if (empty(trim($text))) {
                throw new \Exception("Could not extract text from the uploaded file. It might be empty or unsupported.");
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

    protected function extractTextFromFile(string $filePath, string $extension): string
    {
        if ($extension === 'pdf') {
            $pdf = $this->pdfParser->parseFile($filePath);
            $pages = $pdf->getPages();
            if (count($pages) > 20) {
                throw new \Exception("PDF is too long. Maximum allowed is 20 pages.");
            }
            return (string) $pdf->getText();
        }

        if ($extension === 'docx') {
            return $this->extractDocxText($filePath);
        }

        if ($extension === 'pptx') {
            return $this->extractPptxText($filePath);
        }

        return '';
    }

    protected function extractDocxText(string $filePath): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            return '';
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (!$xml) {
            return '';
        }

        $xml = preg_replace('/<w:tab\/>/', ' ', $xml);
        $xml = preg_replace('/<\/w:p>/', "\n", $xml);
        $text = strip_tags($xml);
        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    protected function extractPptxText(string $filePath): string
    {
        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            return '';
        }

        $slidesText = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (!$name || !preg_match('/^ppt\/slides\/slide\d+\.xml$/', $name)) {
                continue;
            }
            $xml = $zip->getFromIndex($i);
            if (!$xml) {
                continue;
            }
            preg_match_all('/<a:t[^>]*>(.*?)<\/a:t>/s', $xml, $matches);
            if (!empty($matches[1])) {
                $slideText = implode(' ', array_map(fn ($part) => html_entity_decode(strip_tags((string) $part), ENT_QUOTES | ENT_XML1, 'UTF-8'), $matches[1]));
                $slidesText[] = trim((string) preg_replace('/\s+/', ' ', $slideText));
            }
        }

        $zip->close();
        return trim(implode("\n", array_filter($slidesText)));
    }
}

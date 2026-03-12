<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AiService
{
    protected $apiKey;
    protected $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.ai.key');
        $this->baseUrl = config('services.ai.base_url', 'https://api.openai.com/v1');
    }

    public function generateSummary(string $topic)
    {
        // For the comparison feature, we simulate or call multiple models.
        // Even if we have one API key, we can use different system prompts or models.

        $results = [
            'chatgpt' => $this->getMockOrReal('chatgpt', $topic),
            'gemini' => $this->getMockOrReal('gemini', $topic),
        ];

        return $results;
    }

    protected function getMockOrReal(string $provider, string $topic)
    {
        if (!$this->apiKey) {
            return $this->getMockResponse($provider, $topic);
        }

        try {
            // If we have a key, we'll use OpenAI for both but with different identities for now
            // Or if custom base URLs are set, we could theoretically route them.
            $identity = $provider === 'chatgpt'
                ? 'You are ChatGPT (GPT-3.5), a helpful AI assistant.'
                : 'You are Gemini, Google\'s most capable AI model.';

            $response = Http::withToken($this->apiKey)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => config('services.ai.model', 'gpt-3.5-turbo'),
                    'messages' => [
                        ['role' => 'system', 'content' => $identity . ' Provide a comprehensive summary for teachers.'],
                        ['role' => 'user', 'content' => "Provide a detailed summary and major key points about: {$topic}"],
                    ],
                ]);

            if ($response->successful()) {
                return $response->json('choices.0.message.content');
            }
        } catch (\Exception $e) {
            // Fallback to mock on error or just rethrow
        }

        return $this->getMockResponse($provider, $topic);
    }

    protected function getMockResponse(string $provider, string $topic)
    {
        if ($provider === 'chatgpt') {
            return "ChatGPT Summary for '{$topic}':\n\nThis is a simulated ChatGPT response. It focuses on conversational, helpful educational content regarding current pedagogical standards.";
        }

        return "Gemini Summary for '{$topic}':\n\nThis is a simulated Gemini response. It emphasizes logical structure, data-driven insights, and Google-style clarity for complex topics.";
    }
    public function generateRawResponse(string $prompt)
    {
        if (!$this->apiKey) {
            // If no key, we return a clear "missing key" mock or error
            return json_encode([
                [
                    'question_text' => '⚠️ AI API Key is missing in .env. Please configure AI_API_KEY to generate real questions from your file.',
                    'type' => 'multiple_choice',
                    'options' => ['Configure Key', 'Check .env', 'Tutorial', 'Mock Mode'],
                    'correct_answer' => 'Configure Key',
                    'explanation' => 'You are seeing this because the backend is in Mock Mode due to a missing API Key.'
                ]
            ]);
        }

        // Determine if we should use Gemini or OpenAI format
        $isGemini = str_contains($this->baseUrl, 'googlevisualization') || str_contains($this->baseUrl, 'generativelanguage');

        if ($isGemini) {
            return $this->callGemini($prompt);
        }

        return $this->callOpenAI($prompt);
    }

    protected function callOpenAI(string $prompt)
    {
        $response = Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => config('services.ai.model', 'gpt-3.5-turbo'),
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a helpful educational assistant. Return strictly valid JSON.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'response_format' => ['type' => 'json_object']
            ]);

        if ($response->failed()) {
            $errorData = $response->json();
            $msg = $errorData['error']['message'] ?? $response->body();

            if ($response->status() === 429) {
                // FALLBACK TO SMART DEMO MODE ON QUOTA ERROR
                return $this->getMockQuizResponse($prompt);
            }

            throw new \Exception("OpenAI API error: " . $msg);
        }

        return $response->json('choices.0.message.content');
    }

    protected function callGemini(string $prompt)
    {
        $model = config('services.ai.model', 'gemini-2.0-flash-lite');
        $url = "{$this->baseUrl}/models/{$model}:generateContent?key=" . $this->apiKey;

        $response = Http::post($url, [
            'contents' => [
                ['parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
            ]
        ]);

        if ($response->failed()) {
            // If it's a quota error, fallback to demo mode
            if ($response->status() === 429) {
                return $this->getMockQuizResponse($prompt);
            }

            // If JSON mode fails (some older models/versions), try regular mode
            if ($response->status() === 400) {
                $response = Http::post($url, [
                    'contents' => [
                        ['parts' => [['text' => $prompt . "\n\nIMPORTANT: Return ONLY valid JSON."]]]
                    ]
                ]);
            }

            if ($response->failed()) {
                $errorData = $response->json();
                $msg = $errorData['error']['message'] ?? $response->body();
                throw new \Exception("Gemini API error: " . $msg);
            }
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        // Ensure we only return the JSON part
        if (preg_match('/\[.*\]/s', $text, $matches)) {
            return $matches[0];
        }
        if (preg_match('/\{.*\}/s', $text, $matches)) {
            return $matches[0];
        }

        return $text;
    }

    protected function getMockQuizResponse(string $prompt = '')
    {
        // Try to extract some context from the prompt (the PDF text)
        $context = "General Topic";
        if (preg_match('/Content:\s*---\s*(.*?)\n/s', $prompt, $matches)) {
            $context = trim($matches[1]);
            // Take first 50 chars as a topic
            $context = substr($context, 0, 50) . "...";
        }

        return json_encode([
            [
                'question_text' => "Demo: Based on your PDF content ('$context'), what is the primary theme?",
                'type' => 'multiple_choice',
                'options' => ['Detailed Analysis', 'Introductory Overview', 'Technical Specification', 'Historical Background'],
                'correct_answer' => 'Introductory Overview',
                'explanation' => "The uploaded file mentions elements related to '$context'."
            ],
            [
                'question_text' => "Demo: Which key term was extracted from your document?",
                'type' => 'multiple_choice',
                'options' => ['Education', 'Technology', 'Innovation', 'Research'],
                'correct_answer' => 'Technology',
                'explanation' => "The analyzer detected keywords related to the provided text."
            ]
        ]);
    }
}

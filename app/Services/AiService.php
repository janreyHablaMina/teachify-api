<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AiService
{
    protected $apiKey;
    protected $baseUrl;
    protected $provider;
    protected $model;

    public function __construct()
    {
        $this->apiKey = config('services.ai.key');
        $this->baseUrl = rtrim(config('services.ai.base_url', 'https://api.openai.com/v1'), '/');
        $this->provider = strtolower(config('services.ai.provider', 'openai'));
        $this->model = config('services.ai.model', 'gpt-4o-mini');
    }

    public function generateSummary(string $topic)
    {
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
            $identity = $provider === 'chatgpt'
                ? 'You are ChatGPT, a helpful AI assistant.'
                : 'You are Gemini, Google\'s helpful AI assistant.';

            $response = Http::withToken($this->apiKey)
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => $identity . ' Provide a comprehensive summary for teachers.'],
                        ['role' => 'user', 'content' => "Provide a detailed summary and major key points about: {$topic}"],
                    ],
                ]);

            if ($response->successful()) {
                return $response->json('choices.0.message.content');
            }
        } catch (\Exception $e) {
        }

        return $this->getMockResponse($provider, $topic);
    }

    protected function getMockResponse(string $provider, string $topic)
    {
        if ($provider === 'chatgpt') {
            return "ChatGPT Summary for '{$topic}':\n\nThis is a simulated ChatGPT response.";
        }

        return "Gemini Summary for '{$topic}':\n\nThis is a simulated Gemini response.";
    }

    public function generateRawResponse(string $prompt)
    {
        if (! $this->hasConfiguredApiKey()) {
            if (app()->isLocal()) {
                return $this->getMockQuizResponse($prompt);
            }

            throw new \Exception('AI API key is missing. Set AI_API_KEY in the backend .env.');
        }

        if ($this->provider === 'gemini' || str_contains($this->baseUrl, 'generativelanguage.googleapis.com')) {
            return $this->callGemini($prompt);
        }

        return $this->callOpenAI($prompt);
    }

    protected function callOpenAI(string $prompt)
    {
        $response = Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/chat/completions", [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a helpful educational assistant. Return strictly valid JSON.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if ($response->failed()) {
            if ($response->status() === 429) {
                return $this->getMockQuizResponse($prompt);
            }

            $errorData = $response->json();
            $msg = $errorData['error']['message'] ?? $response->body();
            throw new \Exception("OpenAI API error: {$msg}");
        }

        $content = $response->json('choices.0.message.content');
        if (!$content) {
            throw new \Exception('OpenAI returned an empty response.');
        }

        return $content;
    }

    protected function callGemini(string $prompt)
    {
        $model = $this->model ?: 'gemini-2.0-flash-lite';
        $url = "{$this->baseUrl}/models/{$model}:generateContent?key={$this->apiKey}";

        $response = Http::post($url, [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
            ],
        ]);

        if ($response->failed() && $response->status() === 400) {
            $response = Http::post($url, [
                'contents' => [
                    ['parts' => [['text' => $prompt . "\n\nReturn ONLY valid JSON."]]],
                ],
            ]);
        }

        if ($response->failed()) {
            if ($response->status() === 429) {
                return $this->getMockQuizResponse($prompt);
            }

            $errorData = $response->json();
            $msg = $errorData['error']['message'] ?? $response->body();

            if (app()->isLocal() && str_contains(strtolower((string) $msg), 'api key not valid')) {
                return $this->getMockQuizResponse($prompt);
            }

            throw new \Exception("Gemini API error: {$msg}");
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        if (!$text) {
            throw new \Exception('Gemini returned an empty response.');
        }

        if (preg_match('/\[[\s\S]*\]/', $text, $matches)) {
            return $matches[0];
        }

        if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
            return $matches[0];
        }

        return $text;
    }

    protected function hasConfiguredApiKey(): bool
    {
        $key = trim((string) $this->apiKey);

        return $key !== '' && $key !== 'your_api_key_here';
    }

    protected function getMockQuizResponse(string $prompt = '')
    {
        $questionCount = 5;
        if (preg_match('/exactly\s+(\d+)\s+questions/i', $prompt, $matches)) {
            $questionCount = max(1, min((int) $matches[1], 50));
        }

        $content = '';
        if (preg_match('/Content:\s*---\s*(.*?)\s*---/s', $prompt, $matches)) {
            $content = trim((string) $matches[1]);
        }
        $content = preg_replace('/\s+/', ' ', $content ?? '');
        $content = trim((string) $content);

        // Build sentence pool from PDF text and keep meaningful lines only.
        $sentences = preg_split('/(?<=[\.\!\?])\s+/u', $content) ?: [];
        $sentences = array_values(array_filter(array_map('trim', $sentences), function ($s) {
            $len = mb_strlen($s);
            return $len >= 35 && $len <= 220;
        }));

        if (count($sentences) < 4) {
            $seed = mb_substr($content ?: 'the uploaded lesson', 0, 120);
            $items = [];
            for ($i = 1; $i <= $questionCount; $i++) {
                $items[] = [
                    'question_text' => "Fallback Q{$i}: Which statement best matches the uploaded lesson context?",
                    'type' => 'multiple_choice',
                    'options' => [
                        "The lesson focuses on: {$seed}",
                        'The lesson is mainly about weather forecasting only.',
                        'The lesson discusses unrelated celebrity news.',
                        'The lesson is purely random symbols without meaning.',
                    ],
                    'correct_answer' => "The lesson focuses on: {$seed}",
                    'explanation' => 'Demo mode: generated from extracted PDF context because API quota is unavailable.',
                ];
            }

            return json_encode($items);
        }

        $items = [];
        $max = min($questionCount, count($sentences));
        for ($i = 0; $i < $max; $i++) {
            $correct = $sentences[$i];
            $questionText = "Based on the uploaded lesson, which statement is explicitly supported?";

            $distractors = [];
            for ($j = 1; $j <= count($sentences); $j++) {
                $candidate = $sentences[($i + $j) % count($sentences)];
                if ($candidate !== $correct) {
                    $distractors[] = "Not stated directly: {$candidate}";
                }
                if (count($distractors) === 3) {
                    break;
                }
            }

            while (count($distractors) < 3) {
                $distractors[] = 'Not stated directly in the uploaded lesson text.';
            }

            $options = array_merge([$correct], $distractors);
            shuffle($options);

            $items[] = [
                'question_text' => $questionText,
                'type' => 'multiple_choice',
                'options' => $options,
                'correct_answer' => $correct,
                'explanation' => 'Demo mode: generated from extracted PDF sentences because API quota is unavailable.',
            ];
        }

        return json_encode($items);
    }
}

<?php
$key = 'AIzaSyB2js6ti7Ria3qjSoGx3euore_grG5lf0c';
$models = [
    'gemini-1.5-pro',
    'gemini-1.5-flash-8b',
    'gemini-2.0-flash-exp',
    'gemini-pro'
];

foreach ($models as $model) {
    echo "--- Testing Model: $model ---\n";
    $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=$key";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'contents' => [['parts' => [['text' => 'Give me one word.']]]]
    ]));
    
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Status: $status\n";
    echo "Response: $response\n\n";
}

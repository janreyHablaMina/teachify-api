<?php
$apiKey = 'AIzaSyB2js6ti7Ria3qjSoGx3euore_grG5lf0c';
$models = ['gemini-2.0-flash-lite', 'gemini-2.0-flash', 'gemini-2.5-flash'];

foreach ($models as $model) {
    echo "Testing Model: $model\n";
    $url = "https://generativelanguage.googleapis.com/v1/models/$model:generateContent?key=$apiKey";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'contents' => [['parts' => [['text' => 'Hello']]]]
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Status: $httpCode\n";
    if ($httpCode !== 200) {
        $error = json_decode($response, true);
        echo "Error Message: " . ($error['error']['message'] ?? 'Unknown error') . "\n";
    } else {
        echo "Success!\n";
    }
    echo "-------------------\n";
}

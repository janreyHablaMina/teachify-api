<?php
// Testing keys found in quizMe
$keys = [
    'AIzaSyCpA8aWNJXAuAmXhbQBqFPhJICFj836hYU', // Web key
    'AIzaSyBNjktAdNzOouiv23zTalOHKxrPWmOhl7w'  // Android key
];

foreach ($keys as $key) {
    echo "--- Testing Key: $key ---\n";
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=$key";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'contents' => [['parts' => [['text' => 'hi']]]]
    ]));
    
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "Status: $status\n";
    echo "Response: $response\n\n";
}

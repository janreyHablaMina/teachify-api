<?php
$apiKey = 'AIzaSyB2js6ti7Ria3qjSoGx3euore_grG5lf0c';
$url = "https://generativelanguage.googleapis.com/v1beta/models?key=$apiKey";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
if (isset($data['models'])) {
    foreach ($data['models'] as $m) {
        echo $m['name'] . "\n";
    }
} else {
    echo "Error: " . $response . "\n";
}

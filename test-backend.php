<?php

require_once 'vendor/autoload.php';

use Illuminate\Http\Client\Factory as HttpClientFactory;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Test backend connection
$http = new HttpClientFactory();
$ttsBackendUrl = 'https://localhost:3001';

echo "Testing TTS Backend Connection...\n";

try {
    // Test health endpoint
    echo "1. Testing health endpoint...\n";
    $response = $http->timeout(10)->withoutVerifying()->get($ttsBackendUrl . '/api/health');
    if ($response->successful()) {
        echo "   ✓ Health check passed\n";
        print_r($response->json());
    } else {
        echo "   ✗ Health check failed: " . $response->status() . "\n";
    }

    // Test categories endpoint
    echo "\n2. Testing categories endpoint...\n";
    $response = $http->timeout(10)->withoutVerifying()->get($ttsBackendUrl . '/api/category');
    if ($response->successful()) {
        $categories = $response->json();
        echo "   ✓ Categories fetched: " . count($categories) . " categories\n";
        
        foreach ($categories as $index => $category) {
            echo "   Category " . ($index + 1) . ": " . $category['category'] . " (ID: " . $category['_id'] . ")\n";
            
            // Test getting messages for first category
            if ($index === 0) {
                echo "   Testing messages for first category...\n";
                $messagesResponse = $http->timeout(10)->withoutVerifying()->get($ttsBackendUrl . '/api/motivationMessage/category/' . $category['_id']);
                if ($messagesResponse->successful()) {
                    $messagesData = $messagesResponse->json();
                    echo "     ✓ Messages response received\n";
                    echo "     Message data structure: " . json_encode(array_keys($messagesData[0] ?? []), JSON_PRETTY_PRINT) . "\n";
                    
                    // Extract actual messages
                    $messages = [];
                    foreach ($messagesData as $messageRecord) {
                        if (isset($messageRecord['messages']) && is_array($messageRecord['messages'])) {
                            $messages = array_merge($messages, $messageRecord['messages']);
                        }
                    }
                    echo "     Total messages extracted: " . count($messages) . "\n";
                    if (!empty($messages)) {
                        echo "     Sample message: " . $messages[0] . "\n";
                    }
                } else {
                    echo "     ✗ Messages fetch failed: " . $messagesResponse->status() . "\n";
                }
            }
        }
    } else {
        echo "   ✗ Categories fetch failed: " . $response->status() . "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nTest completed.\n";

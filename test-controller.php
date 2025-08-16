<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Create an instance of the controller
$controller = new App\Http\Controllers\Admin\TtsProductController();

// Use reflection to access protected methods
$reflection = new ReflectionClass($controller);

// Test getBackendCategories method
$getBackendCategoriesMethod = $reflection->getMethod('getBackendCategories');
$getBackendCategoriesMethod->setAccessible(true);

echo "Testing getBackendCategories method...\n";

try {
    $categories = $getBackendCategoriesMethod->invoke($controller);
    
    echo "✓ Categories fetched: " . count($categories) . "\n";
    
    foreach ($categories as $category) {
        echo "- " . $category['name'] . " (" . $category['count'] . " messages)\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nTesting completed.\n";

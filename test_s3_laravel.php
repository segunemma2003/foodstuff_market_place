<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Storage;

try {
    echo "Testing S3 connection with Laravel Storage...\n";

    // Test if S3 disk is configured
    $disk = Storage::disk('s3');
    echo "S3 disk created successfully\n";

    // Test bucket access by listing contents
    $files = $disk->files();
    echo "S3 bucket access test: SUCCESS\n";
    echo "Files in bucket: " . count($files) . "\n";

    // Test file upload
    $testContent = 'Hello S3 Test from Laravel! ' . time();
    $testKey = 'test/laravel-test-' . time() . '.txt';

    $result = $disk->put($testKey, $testContent, 'public');
    echo "File upload test: " . ($result ? "SUCCESS" : "FAILED") . "\n";

    if ($result) {
        // Test file download
        $downloadedContent = $disk->get($testKey);
        echo "File download test: " . ($downloadedContent === $testContent ? "SUCCESS" : "FAILED") . "\n";

        // Test file URL
        $url = $disk->url($testKey);
        echo "File URL: " . $url . "\n";

        // Clean up - delete test file
        $disk->delete($testKey);
        echo "File deletion test: SUCCESS\n";
    }

    echo "All S3 tests completed!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

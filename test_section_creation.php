<?php

// Simple test script to debug section creation
$laravelApiUrl = 'https://foodstuff-admin-api-6f0912a00bcb.herokuapp.com';

echo "🧪 Testing Section Creation\n";
echo "===========================\n\n";

// Test 1: Check if Laravel API is reachable
echo "1️⃣ Testing API connectivity...\n";
$healthResponse = file_get_contents("{$laravelApiUrl}/api/v1/health");
if ($healthResponse) {
    echo "✅ Laravel API is reachable\n";
} else {
    echo "❌ Cannot reach Laravel API\n";
    exit(1);
}

echo "\n";

// Test 2: Test section creation
echo "2️⃣ Testing section creation...\n";
$testPhone = '2349036444724';

$data = [
    'whatsapp_number' => $testPhone
];

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode($data)
    ]
]);

$response = file_get_contents("{$laravelApiUrl}/api/v1/whatsapp/create-section", false, $context);

if ($response) {
    $result = json_decode($response, true);
    if ($result['success'] ?? false) {
        echo "✅ Section created successfully!\n";
        echo "   Section ID: " . ($result['section_id'] ?? 'N/A') . "\n";
        echo "   Message: " . ($result['message'] ?? 'N/A') . "\n";
    } else {
        echo "❌ Section creation failed\n";
        echo "   Error: " . ($result['message'] ?? 'Unknown error') . "\n";
    }
} else {
    echo "❌ No response from section creation endpoint\n";
}

echo "\n";

// Test 3: Check if WhatsApp bot can reach Laravel API
echo "3️⃣ Testing WhatsApp bot connectivity to Laravel API...\n";
$whatsappBotUrl = 'https://foodstuff-whatsapp-bot-1aeb07cc3b64.herokuapp.com';

$botStatusResponse = file_get_contents("{$whatsappBotUrl}/health");
if ($botStatusResponse) {
    $botStatus = json_decode($botStatusResponse, true);
    echo "✅ WhatsApp Bot is reachable\n";
    echo "   Laravel API URL: " . ($botStatus['laravel_api_url'] ?? 'N/A') . "\n";
    echo "   WhatsApp Ready: " . ($botStatus['whatsapp_ready'] ? 'Yes' : 'No') . "\n";
} else {
    echo "❌ Cannot reach WhatsApp Bot\n";
}

echo "\n";

// Test 4: Test the full flow (WhatsApp bot creating section)
echo "4️⃣ Testing full flow (WhatsApp bot → Laravel API)...\n";

$testData = [
    'whatsapp_number' => $testPhone
];

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/json',
        'content' => json_encode($testData)
    ]
]);

// This would be the call that the WhatsApp bot makes
$fullFlowResponse = file_get_contents("{$laravelApiUrl}/api/v1/whatsapp/create-section", false, $context);

if ($fullFlowResponse) {
    $fullFlowResult = json_decode($fullFlowResponse, true);
    if ($fullFlowResult['success'] ?? false) {
        echo "✅ Full flow works!\n";
        echo "   Section ID: " . ($fullFlowResult['section_id'] ?? 'N/A') . "\n";

        // Test the frontend URL that would be sent
        $frontendUrl = 'https://marketplace.foodstuff.store';
        $shoppingUrl = "{$frontendUrl}?section_id=" . ($fullFlowResult['section_id'] ?? 'test');
        echo "   Shopping URL: {$shoppingUrl}\n";
    } else {
        echo "❌ Full flow failed\n";
        echo "   Error: " . ($fullFlowResult['message'] ?? 'Unknown error') . "\n";
    }
} else {
    echo "❌ No response in full flow test\n";
}

echo "\n";
echo "🏁 Section Creation Test Complete\n";
echo "================================\n";
echo "If section creation is failing, the issue is likely:\n";
echo "1. Database connectivity\n";
echo "2. Missing database tables\n";
echo "3. Pending migrations\n";
echo "4. Environment configuration\n";
echo "\n";
echo "Next steps:\n";
echo "1. Check Laravel logs: heroku logs --app foodstuff-admin-api --tail\n";
echo "2. Run pending migrations\n";
echo "3. Check database connectivity\n";

<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Http;
use App\Models\WhatsappSession;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Creating Test WhatsApp Session and Sending Message ===\n\n";

// Create a test WhatsApp session
$testNumber = '+2349036444724'; // Using the test number you provided
$sectionId = 'test-section-' . time();

echo "Creating test session...\n";
echo "WhatsApp Number: {$testNumber}\n";
echo "Section ID: {$sectionId}\n\n";

try {
    $session = WhatsappSession::create([
        'whatsapp_number' => $testNumber,
        'session_id' => 'test-session-' . time(),
        'section_id' => $sectionId,
        'status' => 'active',
        'current_step' => 'greeting',
        'last_activity' => now(),
    ]);

    echo "✅ Test session created successfully!\n";
    echo "Session ID: {$session->id}\n\n";

} catch (\Exception $e) {
    echo "❌ Error creating session: " . $e->getMessage() . "\n";
    exit;
}

// Send test message
echo "=== Sending Test Message ===\n";

$whatsappBotUrl = env('WHATSAPP_BOT_URL', 'https://foodstuff-whatsapp-bot-6536aa3f6997.herokuapp.com');
$testMessage = "🧪 TEST MESSAGE: This is a test message from the admin system to verify WhatsApp bot functionality. Please ignore this message.";

$data = [
    'section_id' => $sectionId,
    'status' => 'test',
    'message' => $testMessage,
    'whatsapp_number' => $testNumber,
    'order_number' => 'TEST-' . time(),
];

echo "Sending to WhatsApp Bot URL: {$whatsappBotUrl}\n";
echo "Data: " . json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

try {
    $response = Http::timeout(10)->post($whatsappBotUrl . '/order-status-update', $data);

    if ($response->successful()) {
        echo "✅ SUCCESS: Message sent successfully\n";
        echo "Response: " . $response->body() . "\n";
    } else {
        echo "❌ FAILED: HTTP " . $response->status() . "\n";
        echo "Response: " . $response->body() . "\n";
    }
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";

<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== WhatsApp Sessions Check ===\n\n";

// Get all WhatsApp sessions
$sessions = \App\Models\WhatsappSession::all();

if ($sessions->isEmpty()) {
    echo "No WhatsApp sessions found in database.\n";
    exit;
}

echo "Found " . $sessions->count() . " total sessions:\n\n";

foreach ($sessions as $session) {
    echo "ID: {$session->id}\n";
    echo "Section ID: {$session->section_id}\n";
    echo "WhatsApp Number: {$session->whatsapp_number}\n";
    echo "Status: {$session->status}\n";
    echo "Created: {$session->created_at}\n";
    echo "Last Activity: {$session->last_activity}\n";
    echo "Order ID: " . ($session->order_id ?? 'None') . "\n";
    echo "---\n";
}

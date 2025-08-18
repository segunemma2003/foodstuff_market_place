<?php

// Debug Geolocation Service
echo "🔍 Debugging Geolocation Service\n";
echo "===============================\n\n";

$laravelApiUrl = 'https://foodstuff-admin-api-6f0912a00bcb.herokuapp.com';

// Test 1: Test the geolocation search endpoint
echo "1️⃣ Testing geolocation search endpoint...\n";
$testQueries = ['Ikota', 'Lagos', 'Ikota Lagos', 'Ajah'];

foreach ($testQueries as $query) {
    echo "   Testing query: '{$query}'\n";

    $url = "{$laravelApiUrl}/api/v1/geolocation/search?query=" . urlencode($query) . "&limit=5";
    $response = file_get_contents($url);

    if ($response) {
        $result = json_decode($response, true);
        if ($result['success'] ?? false) {
            if ($result['count'] > 0) {
                echo "   ✅ Found " . $result['count'] . " results\n";
                foreach ($result['data'] as $item) {
                    echo "      - {$item['display_name']} (Lat: {$item['latitude']}, Lng: {$item['longitude']})\n";
                }
            } else {
                echo "   ❌ No results found\n";
            }
        } else {
            echo "   ❌ API Error: " . ($result['message'] ?? 'Unknown error') . "\n";
        }
    } else {
        echo "   ❌ No response\n";
    }

    echo "\n";
}

// Test 2: Test direct OpenStreetMap API
echo "2️⃣ Testing direct OpenStreetMap API...\n";
$nominatimUrl = 'https://nominatim.openstreetmap.org/search?q=Ikota%20Lagos&format=json&limit=5&addressdetails=1&countrycodes=ng';
$nominatimResponse = file_get_contents($nominatimUrl);

if ($nominatimResponse) {
    $nominatimData = json_decode($nominatimResponse, true);
    if (is_array($nominatimData) && count($nominatimData) > 0) {
        echo "   ✅ OpenStreetMap API working - Found " . count($nominatimData) . " results\n";
        foreach ($nominatimData as $item) {
            echo "      - {$item['display_name']} (Lat: {$item['lat']}, Lng: {$item['lon']})\n";
        }
    } else {
        echo "   ❌ OpenStreetMap API returned empty results\n";
    }
} else {
    echo "   ❌ Cannot reach OpenStreetMap API\n";
}

echo "\n";

// Test 3: Test reverse geolocation
echo "3️⃣ Testing reverse geolocation...\n";
$reverseUrl = "{$laravelApiUrl}/api/v1/geolocation/reverse?latitude=6.4473305&longitude=3.5495785";
$reverseResponse = file_get_contents($reverseUrl);

if ($reverseResponse) {
    $reverseResult = json_decode($reverseResponse, true);
    if ($reverseResult['success'] ?? false) {
        echo "   ✅ Reverse geolocation working\n";
        echo "      - {$reverseResult['data']['display_name']}\n";
    } else {
        echo "   ❌ Reverse geolocation failed: " . ($reverseResult['message'] ?? 'Unknown error') . "\n";
    }
} else {
    echo "   ❌ No response from reverse geolocation\n";
}

echo "\n";
echo "🏁 Geolocation Debug Complete\n";
echo "============================\n";
echo "💡 Analysis:\n";
echo "- If OpenStreetMap API works but Laravel API doesn't: HTTP client issue\n";
echo "- If both return empty: Network/configuration issue\n";
echo "- If Laravel API returns errors: Code issue\n";
echo "\n";
echo "🔧 Possible fixes:\n";
echo "1. Check HTTP client configuration\n";
echo "2. Verify network connectivity from Heroku\n";
echo "3. Check if OpenStreetMap is blocking requests\n";
echo "4. Add error logging to the geolocation controller\n";

<?php

// Test Ikota Geolocation Search
echo "🗺️ Testing Ikota Geolocation Search\n";
echo "==================================\n\n";

$laravelApiUrl = 'https://foodstuff-admin-api-6f0912a00bcb.herokuapp.com';

// Different possible Ikota coordinates
$ikotaCoordinates = [
    ['name' => 'Ikota Center', 'lat' => 6.4682, 'lng' => 3.5643],
    ['name' => 'Ikota Estate', 'lat' => 6.45, 'lng' => 3.55],
    ['name' => 'Ikota Junction', 'lat' => 6.47, 'lng' => 3.57],
    ['name' => 'Ikota Main', 'lat' => 6.46, 'lng' => 3.56],
    ['name' => 'Ikota Far', 'lat' => 6.44, 'lng' => 3.54],
];

echo "📍 Testing different Ikota coordinates:\n\n";

foreach ($ikotaCoordinates as $location) {
    echo "Testing {$location['name']} (Lat: {$location['lat']}, Lng: {$location['lng']}):\n";

    // Test WhatsApp section nearby markets API
    $data = [
        'latitude' => $location['lat'],
        'longitude' => $location['lng'],
        'section_id' => 'SEC_1755483000_4de3ce',
        'search' => ''
    ];

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => json_encode($data)
        ]
    ]);

    $response = file_get_contents("{$laravelApiUrl}/api/v1/whatsapp/section/nearby-markets", false, $context);

    if ($response) {
        $result = json_decode($response, true);
        if ($result['success'] ?? false) {
            if ($result['total'] > 0) {
                echo "   ✅ Found " . $result['total'] . " markets\n";
                foreach ($result['markets'] as $market) {
                    echo "      - {$market['name']} ({$market['distance']}km) - Delivery: ₦" . number_format($market['delivery_amount']) . " - Time: {$market['delivery_time']}\n";
                }
            } else {
                echo "   ❌ No markets found within 30km radius\n";
            }
        } else {
            echo "   ❌ API Error: " . ($result['message'] ?? 'Unknown error') . "\n";
        }
    } else {
        echo "   ❌ No response from API\n";
    }

    echo "\n";
}

// Test with different radius values for Ikota
echo "🔍 Testing different radius values for Ikota:\n";
$testLat = 6.4682;
$testLng = 3.5643;
$testRadius = [5, 10, 20, 30, 50, 100];

foreach ($testRadius as $radius) {
    echo "Radius {$radius}km:\n";

    $url = "{$laravelApiUrl}/api/v1/markets/nearby?latitude={$testLat}&longitude={$testLng}&radius={$radius}";
    $response = file_get_contents($url);

    if ($response) {
        $result = json_decode($response, true);
        if ($result['success'] ?? false) {
            if ($result['count'] > 0) {
                echo "   Found " . $result['count'] . " markets\n";
                foreach ($result['data'] as $market) {
                    echo "      - {$market['name']} ({$market['distance']}km)\n";
                }
            } else {
                echo "   No markets found\n";
            }
        } else {
            echo "   Error: " . ($result['message'] ?? 'Unknown error') . "\n";
        }
    } else {
        echo "   No response\n";
    }
}

echo "\n";

// Test search functionality for Ikota
echo "🔎 Testing search functionality for Ikota:\n";
$searchTerms = ['Ikota', 'Ajah', 'Market', 'Lekki'];

foreach ($searchTerms as $term) {
    echo "Searching for '{$term}':\n";

    $data = [
        'latitude' => 6.4682,
        'longitude' => 3.5643,
        'section_id' => 'SEC_1755483000_4de3ce',
        'search' => $term
    ];

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => json_encode($data)
        ]
    ]);

    $response = file_get_contents("{$laravelApiUrl}/api/v1/whatsapp/section/nearby-markets", false, $context);

    if ($response) {
        $result = json_decode($response, true);
        if ($result['success'] ?? false) {
            if ($result['total'] > 0) {
                echo "   Found " . $result['total'] . " markets\n";
                foreach ($result['markets'] as $market) {
                    echo "      - {$market['name']} ({$market['distance']}km)\n";
                }
            } else {
                echo "   No markets found\n";
            }
        } else {
            echo "   Error: " . ($result['message'] ?? 'Unknown error') . "\n";
        }
    } else {
        echo "   No response\n";
    }
}

echo "\n";
echo "🏁 Ikota Geolocation Test Complete\n";
echo "=================================\n";
echo "💡 If Ikota is returning empty results, possible reasons:\n";
echo "1. Your Ikota coordinates are outside the 30km radius from available markets\n";
echo "2. The coordinates you're using are not accurate for Ikota\n";
echo "3. There are no markets in the database near your Ikota coordinates\n";
echo "\n";
echo "🔧 Solutions:\n";
echo "1. Try increasing the search radius to 50km or more\n";
echo "2. Verify your Ikota coordinates are correct\n";
echo "3. Add more markets to the database near Ikota\n";
echo "4. Use the search functionality to find markets by name\n";
echo "\n";
echo "📊 Current market locations:\n";
echo "- Ajah Market: Lat 6.46815800, Lng 3.56431310 (near Ikota)\n";
echo "- This market should be found when searching from Ikota coordinates\n";

<?php

// Test database connection
echo "🔍 Testing Database Connection\n";
echo "==============================\n\n";

// Test 1: Check if we can connect to the database
try {
    $pdo = new PDO(
        'mysql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_DATABASE'),
        getenv('DB_USERNAME'),
        getenv('DB_PASSWORD')
    );
    echo "✅ Database connection successful\n";

    // Test 2: Check if whatsapp_sessions table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'whatsapp_sessions'");
    if ($stmt->rowCount() > 0) {
        echo "✅ whatsapp_sessions table exists\n";

        // Test 3: Check table structure
        $stmt = $pdo->query("DESCRIBE whatsapp_sessions");
        echo "📋 Table structure:\n";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "   - {$row['Field']}: {$row['Type']} " . ($row['Null'] === 'NO' ? 'NOT NULL' : 'NULL') . "\n";
        }
    } else {
        echo "❌ whatsapp_sessions table does not exist\n";
    }

} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 4: Check environment variables
echo "🔧 Environment Variables:\n";
echo "   DB_HOST: " . (getenv('DB_HOST') ?: 'NOT SET') . "\n";
echo "   DB_DATABASE: " . (getenv('DB_DATABASE') ?: 'NOT SET') . "\n";
echo "   DB_USERNAME: " . (getenv('DB_USERNAME') ?: 'NOT SET') . "\n";
echo "   DB_PASSWORD: " . (getenv('DB_PASSWORD') ? 'SET' : 'NOT SET') . "\n";

echo "\n";
echo "🏁 Database Test Complete\n";

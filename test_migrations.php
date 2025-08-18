<?php

// Test migrations and database setup
echo "🔧 Testing Database Migrations\n";
echo "==============================\n\n";

// Test 1: Check if we can connect to the database
try {
    $pdo = new PDO(getenv('DATABASE_URL'));
    echo "✅ Database connection successful\n";

    // Test 2: Check if migrations table exists
    $stmt = $pdo->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'migrations')");
    $migrationsExist = $stmt->fetchColumn();

    if ($migrationsExist) {
        echo "✅ Migrations table exists\n";

        // Check if any migrations have been run
        $stmt = $pdo->query("SELECT COUNT(*) FROM migrations");
        $migrationCount = $stmt->fetchColumn();
        echo "📊 Migrations run: {$migrationCount}\n";

        if ($migrationCount == 0) {
            echo "⚠️ No migrations have been run yet\n";
        }
    } else {
        echo "❌ Migrations table does not exist\n";
    }

    // Test 3: Check if whatsapp_sessions table exists
    $stmt = $pdo->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'whatsapp_sessions')");
    $sessionsExist = $stmt->fetchColumn();

    if ($sessionsExist) {
        echo "✅ whatsapp_sessions table exists\n";

        // Check table structure
        $stmt = $pdo->query("SELECT column_name, data_type, is_nullable FROM information_schema.columns WHERE table_name = 'whatsapp_sessions' ORDER BY ordinal_position");
        echo "📋 Table structure:\n";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "   - {$row['column_name']}: {$row['data_type']} " . ($row['is_nullable'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
        }
    } else {
        echo "❌ whatsapp_sessions table does not exist\n";
    }

} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

echo "\n";
echo "🏁 Migration Test Complete\n";
echo "========================\n";
echo "If tables don't exist, run: heroku run php artisan migrate --app foodstuff-admin-api\n";

<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

try {
    // Create admin user
    $admin = User::firstOrCreate(
        ['email' => 'admin@foodstuff.store'],
        [
            'name' => 'FoodStuff Admin',
            'email' => 'admin@foodstuff.store',
            'password' => Hash::make('admin123'),
            'email_verified_at' => now(),
        ]
    );

    echo "✅ Admin user ready!\n";
    echo "Email: admin@foodstuff.store\n";
    echo "Password: admin123\n";

    echo "\n🔗 API Endpoints:\n";
    echo "Base URL: https://foodstuff-store-api.herokuapp.com/api/v1\n";
    echo "Login: POST /auth/login\n";
    echo "Markets: GET /admin/markets\n";
    echo "Agents: GET /admin/agents\n";
    echo "Products: GET /admin/products\n";
    echo "Orders: GET /admin/orders\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

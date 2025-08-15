<?php

namespace Database\Seeders;

use App\Models\MarketProduct;
use Illuminate\Database\Seeder;

class MarketProductSeeder extends Seeder
{
    public function run(): void
    {
        $marketProducts = [
            // Central Market Lagos (Market ID: 1) - Agent 1 (John Doe)
            ['market_id' => 1, 'product_id' => 1, 'agent_id' => 1, 'price' => 1200.00, 'stock_quantity' => 100, 'is_available' => true], // Rice
            ['market_id' => 1, 'product_id' => 2, 'agent_id' => 1, 'price' => 800.00, 'stock_quantity' => 50, 'is_available' => true], // Beans
            ['market_id' => 1, 'product_id' => 5, 'agent_id' => 1, 'price' => 500.00, 'stock_quantity' => 30, 'is_available' => true], // Tomatoes
            ['market_id' => 1, 'product_id' => 6, 'agent_id' => 1, 'price' => 300.00, 'stock_quantity' => 40, 'is_available' => true], // Onions
            ['market_id' => 1, 'product_id' => 10, 'agent_id' => 1, 'price' => 400.00, 'stock_quantity' => 25, 'is_available' => true], // Bananas
            ['market_id' => 1, 'product_id' => 14, 'agent_id' => 1, 'price' => 2500.00, 'stock_quantity' => 20, 'is_available' => true], // Chicken
            ['market_id' => 1, 'product_id' => 18, 'agent_id' => 1, 'price' => 800.00, 'stock_quantity' => 15, 'is_available' => true], // Milk
            ['market_id' => 1, 'product_id' => 22, 'agent_id' => 1, 'price' => 150.00, 'stock_quantity' => 60, 'is_available' => true], // Salt
            ['market_id' => 1, 'product_id' => 23, 'agent_id' => 1, 'price' => 450.00, 'stock_quantity' => 35, 'is_available' => true], // Sugar
            ['market_id' => 1, 'product_id' => 28, 'agent_id' => 1, 'price' => 200.00, 'stock_quantity' => 45, 'is_available' => true], // Groundnuts

            // Central Market Lagos (Market ID: 1) - Agent 2 (Jane Smith)
            ['market_id' => 1, 'product_id' => 3, 'agent_id' => 2, 'price' => 400.00, 'stock_quantity' => 30, 'is_available' => true], // Corn
            ['market_id' => 1, 'product_id' => 4, 'agent_id' => 2, 'price' => 600.00, 'stock_quantity' => 25, 'is_available' => true], // Wheat Flour
            ['market_id' => 1, 'product_id' => 7, 'agent_id' => 2, 'price' => 600.00, 'stock_quantity' => 20, 'is_available' => true], // Pepper
            ['market_id' => 1, 'product_id' => 8, 'agent_id' => 2, 'price' => 350.00, 'stock_quantity' => 15, 'is_available' => true], // Carrots
            ['market_id' => 1, 'product_id' => 11, 'agent_id' => 2, 'price' => 300.00, 'stock_quantity' => 20, 'is_available' => true], // Oranges
            ['market_id' => 1, 'product_id' => 15, 'agent_id' => 2, 'price' => 3000.00, 'stock_quantity' => 15, 'is_available' => true], // Beef
            ['market_id' => 1, 'product_id' => 19, 'agent_id' => 2, 'price' => 1200.00, 'stock_quantity' => 10, 'is_available' => true], // Cheese
            ['market_id' => 1, 'product_id' => 24, 'agent_id' => 2, 'price' => 800.00, 'stock_quantity' => 12, 'is_available' => true], // Garlic
            ['market_id' => 1, 'product_id' => 25, 'agent_id' => 2, 'price' => 600.00, 'stock_quantity' => 18, 'is_available' => true], // Ginger
            ['market_id' => 1, 'product_id' => 29, 'agent_id' => 2, 'price' => 300.00, 'stock_quantity' => 25, 'is_available' => true], // Biscuits

            // Victoria Island Market (Market ID: 2) - Agent 3 (Mike Johnson)
            ['market_id' => 2, 'product_id' => 1, 'agent_id' => 3, 'price' => 1300.00, 'stock_quantity' => 80, 'is_available' => true], // Rice
            ['market_id' => 2, 'product_id' => 5, 'agent_id' => 3, 'price' => 550.00, 'stock_quantity' => 25, 'is_available' => true], // Tomatoes
            ['market_id' => 2, 'product_id' => 9, 'agent_id' => 3, 'price' => 200.00, 'stock_quantity' => 20, 'is_available' => true], // Cabbage
            ['market_id' => 2, 'product_id' => 12, 'agent_id' => 3, 'price' => 800.00, 'stock_quantity' => 15, 'is_available' => true], // Apples
            ['market_id' => 2, 'product_id' => 13, 'agent_id' => 3, 'price' => 400.00, 'stock_quantity' => 18, 'is_available' => true], // Mangoes
            ['market_id' => 2, 'product_id' => 16, 'agent_id' => 3, 'price' => 1800.00, 'stock_quantity' => 12, 'is_available' => true], // Fish
            ['market_id' => 2, 'product_id' => 17, 'agent_id' => 3, 'price' => 50.00, 'stock_quantity' => 100, 'is_available' => true], // Eggs
            ['market_id' => 2, 'product_id' => 20, 'agent_id' => 3, 'price' => 500.00, 'stock_quantity' => 8, 'is_available' => true], // Yogurt
            ['market_id' => 2, 'product_id' => 26, 'agent_id' => 3, 'price' => 1200.00, 'stock_quantity' => 10, 'is_available' => true], // Orange Juice
            ['market_id' => 2, 'product_id' => 30, 'agent_id' => 3, 'price' => 400.00, 'stock_quantity' => 20, 'is_available' => true], // Chips

            // Victoria Island Market (Market ID: 2) - Agent 4 (Sarah Williams)
            ['market_id' => 2, 'product_id' => 2, 'agent_id' => 4, 'price' => 850.00, 'stock_quantity' => 40, 'is_available' => true], // Beans
            ['market_id' => 2, 'product_id' => 6, 'agent_id' => 4, 'price' => 320.00, 'stock_quantity' => 35, 'is_available' => true], // Onions
            ['market_id' => 2, 'product_id' => 10, 'agent_id' => 4, 'price' => 450.00, 'stock_quantity' => 20, 'is_available' => true], // Bananas
            ['market_id' => 2, 'product_id' => 14, 'agent_id' => 4, 'price' => 2700.00, 'stock_quantity' => 15, 'is_available' => true], // Chicken
            ['market_id' => 2, 'product_id' => 18, 'agent_id' => 4, 'price' => 900.00, 'stock_quantity' => 12, 'is_available' => true], // Milk
            ['market_id' => 2, 'product_id' => 22, 'agent_id' => 4, 'price' => 160.00, 'stock_quantity' => 50, 'is_available' => true], // Salt
            ['market_id' => 2, 'product_id' => 23, 'agent_id' => 4, 'price' => 480.00, 'stock_quantity' => 30, 'is_available' => true], // Sugar
            ['market_id' => 2, 'product_id' => 27, 'agent_id' => 4, 'price' => 150.00, 'stock_quantity' => 40, 'is_available' => true], // Water
            ['market_id' => 2, 'product_id' => 28, 'agent_id' => 4, 'price' => 220.00, 'stock_quantity' => 40, 'is_available' => true], // Groundnuts

            // Ikeja Market (Market ID: 3) - Agent 5 (David Brown)
            ['market_id' => 3, 'product_id' => 1, 'agent_id' => 5, 'price' => 1150.00, 'stock_quantity' => 90, 'is_available' => true], // Rice
            ['market_id' => 3, 'product_id' => 3, 'agent_id' => 5, 'price' => 380.00, 'stock_quantity' => 35, 'is_available' => true], // Corn
            ['market_id' => 3, 'product_id' => 5, 'agent_id' => 5, 'price' => 480.00, 'stock_quantity' => 28, 'is_available' => true], // Tomatoes
            ['market_id' => 3, 'product_id' => 7, 'agent_id' => 5, 'price' => 550.00, 'stock_quantity' => 18, 'is_available' => true], // Pepper
            ['market_id' => 3, 'product_id' => 11, 'agent_id' => 5, 'price' => 280.00, 'stock_quantity' => 22, 'is_available' => true], // Oranges
            ['market_id' => 3, 'product_id' => 15, 'agent_id' => 5, 'price' => 2800.00, 'stock_quantity' => 12, 'is_available' => true], // Beef
            ['market_id' => 3, 'product_id' => 17, 'agent_id' => 5, 'price' => 45.00, 'stock_quantity' => 120, 'is_available' => true], // Eggs
            ['market_id' => 3, 'product_id' => 24, 'agent_id' => 5, 'price' => 750.00, 'stock_quantity' => 15, 'is_available' => true], // Garlic
            ['market_id' => 3, 'product_id' => 26, 'agent_id' => 5, 'price' => 1100.00, 'stock_quantity' => 12, 'is_available' => true], // Orange Juice
            ['market_id' => 3, 'product_id' => 29, 'agent_id' => 5, 'price' => 280.00, 'stock_quantity' => 30, 'is_available' => true], // Biscuits
        ];

        foreach ($marketProducts as $marketProduct) {
            MarketProduct::create($marketProduct);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            // Grains & Cereals
            ['name' => 'Rice', 'category_id' => 1, 'description' => 'Premium long grain rice', 'unit' => 'kg'],
            ['name' => 'Beans', 'category_id' => 1, 'description' => 'Brown beans', 'unit' => 'kg'],
            ['name' => 'Corn', 'category_id' => 1, 'description' => 'Fresh corn', 'unit' => 'kg'],
            ['name' => 'Wheat Flour', 'category_id' => 1, 'description' => 'All-purpose wheat flour', 'unit' => 'kg'],

            // Vegetables
            ['name' => 'Tomatoes', 'category_id' => 2, 'description' => 'Fresh red tomatoes', 'unit' => 'kg'],
            ['name' => 'Onions', 'category_id' => 2, 'description' => 'Fresh onions', 'unit' => 'kg'],
            ['name' => 'Pepper', 'category_id' => 2, 'description' => 'Fresh red pepper', 'unit' => 'kg'],
            ['name' => 'Carrots', 'category_id' => 2, 'description' => 'Fresh carrots', 'unit' => 'kg'],
            ['name' => 'Cabbage', 'category_id' => 2, 'description' => 'Fresh cabbage', 'unit' => 'pieces'],

            // Fruits
            ['name' => 'Bananas', 'category_id' => 3, 'description' => 'Fresh yellow bananas', 'unit' => 'kg'],
            ['name' => 'Oranges', 'category_id' => 3, 'description' => 'Sweet oranges', 'unit' => 'kg'],
            ['name' => 'Apples', 'category_id' => 3, 'description' => 'Red apples', 'unit' => 'kg'],
            ['name' => 'Mangoes', 'category_id' => 3, 'description' => 'Sweet mangoes', 'unit' => 'kg'],

            // Meat & Fish
            ['name' => 'Chicken', 'category_id' => 4, 'description' => 'Fresh chicken meat', 'unit' => 'kg'],
            ['name' => 'Beef', 'category_id' => 4, 'description' => 'Fresh beef meat', 'unit' => 'kg'],
            ['name' => 'Fish', 'category_id' => 4, 'description' => 'Fresh fish', 'unit' => 'kg'],
            ['name' => 'Eggs', 'category_id' => 4, 'description' => 'Fresh chicken eggs', 'unit' => 'pieces'],

            // Dairy & Eggs
            ['name' => 'Milk', 'category_id' => 5, 'description' => 'Fresh cow milk', 'unit' => 'liters'],
            ['name' => 'Cheese', 'category_id' => 5, 'description' => 'Cheddar cheese', 'unit' => 'kg'],
            ['name' => 'Yogurt', 'category_id' => 5, 'description' => 'Plain yogurt', 'unit' => 'liters'],

            // Spices & Seasonings
            ['name' => 'Salt', 'category_id' => 6, 'description' => 'Table salt', 'unit' => 'kg'],
            ['name' => 'Sugar', 'category_id' => 6, 'description' => 'Granulated sugar', 'unit' => 'kg'],
            ['name' => 'Garlic', 'category_id' => 6, 'description' => 'Fresh garlic', 'unit' => 'kg'],
            ['name' => 'Ginger', 'category_id' => 6, 'description' => 'Fresh ginger', 'unit' => 'kg'],

            // Beverages
            ['name' => 'Orange Juice', 'category_id' => 7, 'description' => 'Fresh orange juice', 'unit' => 'liters'],
            ['name' => 'Water', 'category_id' => 7, 'description' => 'Bottled water', 'unit' => 'liters'],
            ['name' => 'Tea', 'category_id' => 7, 'description' => 'Black tea bags', 'unit' => 'packets'],

            // Snacks
            ['name' => 'Groundnuts', 'category_id' => 8, 'description' => 'Roasted groundnuts', 'unit' => 'kg'],
            ['name' => 'Biscuits', 'category_id' => 8, 'description' => 'Sweet biscuits', 'unit' => 'packets'],
            ['name' => 'Chips', 'category_id' => 8, 'description' => 'Potato chips', 'unit' => 'packets'],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}

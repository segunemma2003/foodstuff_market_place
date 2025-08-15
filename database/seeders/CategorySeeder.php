<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Grains & Cereals',
                'description' => 'Rice, beans, corn, and other grains',
                'image' => 'grains.jpg',
                'is_active' => true,
            ],
            [
                'name' => 'Vegetables',
                'description' => 'Fresh vegetables and greens',
                'image' => 'vegetables.jpg',
                'is_active' => true,
            ],
            [
                'name' => 'Fruits',
                'description' => 'Fresh fruits and berries',
                'image' => 'fruits.jpg',
                'is_active' => true,
            ],
            [
                'name' => 'Meat & Fish',
                'description' => 'Fresh meat, poultry, and fish',
                'image' => 'meat.jpg',
                'is_active' => true,
            ],
            [
                'name' => 'Dairy & Eggs',
                'description' => 'Milk, cheese, eggs, and dairy products',
                'image' => 'dairy.jpg',
                'is_active' => true,
            ],
            [
                'name' => 'Spices & Seasonings',
                'description' => 'Herbs, spices, and seasonings',
                'image' => 'spices.jpg',
                'is_active' => true,
            ],
            [
                'name' => 'Beverages',
                'description' => 'Drinks, juices, and beverages',
                'image' => 'beverages.jpg',
                'is_active' => true,
            ],
            [
                'name' => 'Snacks',
                'description' => 'Snacks, nuts, and treats',
                'image' => 'snacks.jpg',
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}

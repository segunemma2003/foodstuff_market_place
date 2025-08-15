<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Market;
use App\Models\Product;
use App\Models\Category;
use App\Models\Agent;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run seeders for test data
        $this->seed([
            \Database\Seeders\MarketSeeder::class,
            \Database\Seeders\CategorySeeder::class,
            \Database\Seeders\ProductSeeder::class,
            \Database\Seeders\AgentSeeder::class,
            \Database\Seeders\MarketProductSeeder::class,
        ]);
    }

    public function test_nearby_markets_endpoint(): void
    {
        $response = $this->getJson('/api/v1/markets/nearby?latitude=6.5244&longitude=3.3792&radius=5');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'address',
                            'latitude',
                            'longitude',
                            'distance',
                            'phone',
                            'email',
                        ]
                    ],
                    'count'
                ]);
    }

    public function test_categories_endpoint(): void
    {
        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'description',
                            'image',
                            'products_count',
                        ]
                    ]
                ]);
    }

    public function test_product_search_endpoint(): void
    {
        $response = $this->getJson('/api/v1/products/search?query=rice');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'description',
                            'image',
                            'unit',
                            'category' => [
                                'id',
                                'name',
                            ]
                        ]
                    ],
                    'count'
                ]);
    }

    public function test_market_products_endpoint(): void
    {
        $market = Market::first();

        if ($market) {
            $response = $this->getJson("/api/v1/markets/{$market->id}/products");

            $response->assertStatus(200)
                    ->assertJsonStructure([
                        'success',
                        'data' => [
                            '*' => [
                                'id',
                                'product_id',
                                'name',
                                'description',
                                'image',
                                'unit',
                                'price',
                                'stock_quantity',
                                'category' => [
                                    'id',
                                    'name',
                                ],
                                'agent' => [
                                    'id',
                                    'name',
                                ]
                            ]
                        ],
                        'market' => [
                            'id',
                            'name',
                            'address',
                        ]
                    ]);
        }
    }

    public function test_geolocation_search_endpoint(): void
    {
        $response = $this->getJson('/api/v1/geolocation/search?query=Lagos');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'display_name',
                            'latitude',
                            'longitude',
                            'type',
                            'importance',
                            'address',
                        ]
                    ],
                    'count'
                ]);
    }

    public function test_admin_login_endpoint(): void
    {
        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@foodstuff.store',
            'password' => 'admin123',
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'token',
                        'user' => [
                            'id',
                            'name',
                            'email',
                            'role',
                        ]
                    ]
                ]);
    }

    public function test_agent_login_endpoint(): void
    {
        $response = $this->postJson('/api/v1/agent/login', [
            'email' => 'john.doe@foodstuff.store',
            'password' => 'john',
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'token',
                        'user' => [
                            'id',
                            'name',
                            'email',
                            'phone',
                            'market',
                            'role',
                        ]
                    ]
                ]);
    }
}

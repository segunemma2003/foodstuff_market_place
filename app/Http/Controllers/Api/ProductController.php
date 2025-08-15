<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    /**
     * Product autocomplete search
     */
    public function autocomplete(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:2|max:100',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        $query = Product::with('category')
            ->where('is_active', true);

        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        $products = $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->query . '%')
                  ->orWhere('description', 'like', '%' . $request->query . '%');
            })
            ->limit(10)
            ->get(['id', 'name', 'description', 'unit', 'category_id']);

        return response()->json([
            'success' => true,
            'data' => $products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'unit' => $product->unit,
                    'category' => [
                        'id' => $product->category->id,
                        'name' => $product->category->name,
                    ],
                ];
            }),
            'count' => $products->count(),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:2|max:255',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        $query = Product::with('category')
            ->where('is_active', true);

        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        $products = $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->query . '%')
                  ->orWhere('description', 'like', '%' . $request->query . '%');
            })
            ->get();

        return response()->json([
            'success' => true,
            'data' => $products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'unit' => $product->unit,
                    'image' => $product->image,
                    'category' => [
                        'id' => $product->category->id,
                        'name' => $product->category->name,
                    ],
                ];
            }),
            'count' => $products->count(),
        ]);
    }

    public function getCategories(): JsonResponse
    {
        $categories = Category::withCount('products')
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'description' => $category->description,
                    'image' => $category->image,
                    'products_count' => $category->products_count,
                ];
            }),
        ]);
    }
}

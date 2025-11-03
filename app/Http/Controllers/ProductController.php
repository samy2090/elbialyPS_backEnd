<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    private ProductService $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }

    /**
     * Display a listing of the products.
     */
    public function index(): JsonResponse
    {
        $products = $this->productService->getAllProducts();
        return response()->json($products);
    }

    /**
     * Display the specified product.
     */
    public function show(int $id): JsonResponse
    {
        $product = $this->productService->getProduct($id);
        
        if (!$product) {
            return response()->json(['message' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json($product);
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(CreateProductRequest $request): JsonResponse
    {
        $product = $this->productService->createProduct($request->validated());
        return response()->json($product, Response::HTTP_CREATED);
    }

    /**
     * Update the specified product in storage.
     */
    public function update(UpdateProductRequest $request, int $id): JsonResponse
    {
        $success = $this->productService->updateProduct($id, $request->validated());
        
        if (!$success) {
            return response()->json(['message' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['message' => 'Product updated successfully']);
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $success = $this->productService->deleteProduct($id);
        
        if (!$success) {
            return response()->json(['message' => 'Product not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['message' => 'Product deleted successfully']);
    }
}

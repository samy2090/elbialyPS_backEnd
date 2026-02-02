<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
     *
     * Query parameters (optional):
     * - search: filter by product name or SKU (partial match)
     * - paginate: when true/1, returns paginated results; otherwise returns all (max 500)
     * - per_page: items per page when paginated (default: 10, max: 100)
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->filled('search') ? trim($request->search) : null;

        if ($request->boolean('paginate')) {
            $perPage = min((int) $request->get('per_page', 10), 100);
            $perPage = max($perPage, 1);
            $products = $this->productService->getFilteredProducts($search, true, $perPage);
            return response()->json([
                'status' => 'success',
                'data' => $products,
            ]);
        }

        $maxLimit = 500;
        $products = $this->productService->getFilteredProducts($search, false, $maxLimit);
        return response()->json([
            'status' => 'success',
            'data' => $products,
            'count' => $products->count(),
            'note' => $products->count() >= $maxLimit
                ? "Results limited to {$maxLimit} records. Use paginate=true for full results."
                : null,
        ]);
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

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseCategoryRequest;
use App\Http\Requests\UpdateExpenseCategoryRequest;
use App\Services\ExpenseCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ExpenseCategoryController extends Controller
{
    private ExpenseCategoryService $categoryService;

    public function __construct(ExpenseCategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
    }

    /**
     * Display all categories
     */
    public function index(): JsonResponse
    {
        $categories = $this->categoryService->getAllCategories();
        return response()->json(['data' => $categories]);
    }

    /**
     * Display all categories (paginated)
     */
    public function indexPaginated(): JsonResponse
    {
        $perPage = request()->get('per_page', 15);
        $categories = $this->categoryService->getAllCategoriesPaginated($perPage);
        return response()->json($categories);
    }

    /**
     * Display a specific category
     */
    public function show(int $id): JsonResponse
    {
        $category = $this->categoryService->getCategory($id);
        
        if (!$category) {
            return response()->json(['message' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['data' => $category]);
    }

    /**
     * Get main categories
     */
    public function getMainCategories(): JsonResponse
    {
        $categories = $this->categoryService->getMainCategories();
        return response()->json(['data' => $categories]);
    }

    /**
     * Get sub-categories for a parent
     */
    public function getSubCategories(int $parentId): JsonResponse
    {
        $categories = $this->categoryService->getSubCategories($parentId);
        return response()->json(['data' => $categories]);
    }

    /**
     * Get active categories
     */
    public function getActiveCategories(): JsonResponse
    {
        $categories = $this->categoryService->getActiveCategories();
        return response()->json(['data' => $categories]);
    }

    /**
     * Store a new category
     */
    public function store(StoreExpenseCategoryRequest $request): JsonResponse
    {
        try {
            $category = $this->categoryService->createCategory($request->validated());
            return response()->json([
                'message' => 'Category created successfully',
                'data' => $category
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Update a category
     */
    public function update(UpdateExpenseCategoryRequest $request, int $id): JsonResponse
    {
        try {
            $success = $this->categoryService->updateCategory($id, $request->validated());
            
            if (!$success) {
                return response()->json(['message' => 'Category not found'], Response::HTTP_NOT_FOUND);
            }

            $category = $this->categoryService->getCategory($id);
            
            return response()->json([
                'message' => 'Category updated successfully',
                'data' => $category
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Delete a category
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $success = $this->categoryService->deleteCategory($id);
            
            if (!$success) {
                return response()->json(['message' => 'Category not found'], Response::HTTP_NOT_FOUND);
            }

            return response()->json(['message' => 'Category deleted successfully']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Deactivate a category
     */
    public function deactivate(int $id): JsonResponse
    {
        $success = $this->categoryService->deactivateCategory($id);
        
        if (!$success) {
            return response()->json(['message' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['message' => 'Category deactivated successfully']);
    }

    /**
     * Activate a category
     */
    public function activate(int $id): JsonResponse
    {
        $success = $this->categoryService->activateCategory($id);
        
        if (!$success) {
            return response()->json(['message' => 'Category not found'], Response::HTTP_NOT_FOUND);
        }

        return response()->json(['message' => 'Category activated successfully']);
    }
}

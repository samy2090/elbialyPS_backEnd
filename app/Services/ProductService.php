<?php

namespace App\Services;

use App\Models\Product;
use App\Repositories\ProductRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductService
{
    private ProductRepositoryInterface $productRepository;

    public function __construct(ProductRepositoryInterface $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function getAllProducts(int $perPage = 10): LengthAwarePaginator
    {
        return $this->productRepository->getAllPaginated($perPage);
    }

    /**
     * Get products with optional search filter (name or SKU), optional category filter, and optional pagination.
     * @return LengthAwarePaginator|\Illuminate\Database\Eloquent\Collection
     */
    public function getFilteredProducts(?string $search = null, ?string $category = null, bool $paginate = true, int $perPage = 10): LengthAwarePaginator|\Illuminate\Database\Eloquent\Collection
    {
        return $this->productRepository->getFiltered($search, $category, $paginate, $perPage);
    }

    public function getProduct(int $id): ?Product
    {
        return $this->productRepository->getById($id);
    }

    /**
     * Get distinct product categories (for expense UI: filter products by category).
     */
    public function getProductCategories(): array
    {
        return Product::select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->values()
            ->toArray();
    }

    public function createProduct(array $data): Product
    {
        if (empty($data['sku'])) {
            $data['sku'] = $this->generateSkuFromName($data['name'] ?? '');
        }

        return $this->productRepository->create($data);
    }

    /**
     * Generate a unique SKU from the product name.
     * Format: UPPERCASE_PREFIX + 3-digit number (e.g. OREO001, COKE002).
     */
    private function generateSkuFromName(string $name): string
    {
        $prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $name));
        $prefix = substr($prefix, 0, 6) ?: 'PRD';

        $lastProduct = Product::where('sku', 'like', $prefix . '%')
            ->orderBy('sku', 'desc')
            ->first();

        $nextNumber = 1;
        if ($lastProduct && preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $lastProduct->sku, $m)) {
            $nextNumber = (int) $m[1] + 1;
        }

        return $prefix . str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);
    }

    public function updateProduct(int $id, array $data): bool
    {
        return $this->productRepository->update($id, $data);
    }

    public function deleteProduct(int $id): bool
    {
        return $this->productRepository->delete($id);
    }
}
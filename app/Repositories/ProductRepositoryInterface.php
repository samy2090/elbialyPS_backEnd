<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface ProductRepositoryInterface
{
    public function getAllPaginated(int $perPage = 10): LengthAwarePaginator;

    /**
     * Get products with optional search filter (name or SKU), optional category filter, and optional pagination.
     * @return LengthAwarePaginator|Collection
     */
    public function getFiltered(?string $search = null, ?string $category = null, bool $paginate = true, int $perPage = 10): LengthAwarePaginator|Collection;
    public function getById(int $id): ?Product;
    public function create(array $data): Product;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
}
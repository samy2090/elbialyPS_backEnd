<?php

namespace App\Repositories;

use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface ExpenseCategoryRepositoryInterface
{
    public function getAll(): Collection;
    public function getAllPaginated(int $perPage = 15): LengthAwarePaginator;
    public function getById(int $id): ?ExpenseCategory;
    public function getMainCategories(): Collection;
    public function getSubCategories(int $parentId): Collection;
    public function getActiveCategories(): Collection;
    public function create(array $data): ExpenseCategory;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
}

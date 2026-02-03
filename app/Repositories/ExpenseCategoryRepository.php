<?php

namespace App\Repositories;

use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class ExpenseCategoryRepository implements ExpenseCategoryRepositoryInterface
{
    public function getAll(): Collection
    {
        return ExpenseCategory::with(['parent', 'children'])
            ->orderBy('name')
            ->get();
    }

    public function getAllPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return ExpenseCategory::with(['parent', 'children'])
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function getById(int $id): ?ExpenseCategory
    {
        return ExpenseCategory::with(['parent', 'children', 'expenses', 'creator', 'updater'])
            ->find($id);
    }

    public function getMainCategories(): Collection
    {
        return ExpenseCategory::mainCategories()
            ->with('children')
            ->orderBy('name')
            ->get();
    }

    public function getSubCategories(int $parentId): Collection
    {
        return ExpenseCategory::where('parent_id', $parentId)
            ->orderBy('name')
            ->get();
    }

    public function getActiveCategories(): Collection
    {
        return ExpenseCategory::active()
            ->with(['parent', 'children'])
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): ExpenseCategory
    {
        return ExpenseCategory::create($data);
    }

    public function update(int $id, array $data): bool
    {
        return ExpenseCategory::where('id', $id)->update($data);
    }

    public function delete(int $id): bool
    {
        $category = ExpenseCategory::find($id);
        if (!$category) {
            return false;
        }
        return $category->delete();
    }
}
